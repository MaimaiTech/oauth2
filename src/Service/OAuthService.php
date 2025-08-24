<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace Plugin\MaimaiTech\OAuth2\Service;

use App\Model\Permission\User;
use App\Repository\Permission\UserRepository;
use App\Service\PassportService;
use Carbon\Carbon;
use Hyperf\Collection\Collection;
use Mine\Jwt\Factory;
use Mine\JwtAuth\Event\UserLoginEvent;
use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;
use Plugin\MaimaiTech\OAuth2\Model\UserOAuthAccount;
use Plugin\MaimaiTech\OAuth2\Repository\OAuthStateRepository;
use Plugin\MaimaiTech\OAuth2\Repository\UserOAuthAccountRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Core OAuth2 service for handling OAuth flows and account management.
 */
final class OAuthService
{
    public function __construct(
        private readonly ProviderService $providerService,
        private readonly UserOAuthAccountRepository $accountRepository,
        private readonly OAuthStateRepository $stateRepository,
        private readonly PassportService $passportService,
        private readonly UserRepository $userRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Factory $jwtFactory
    ) {}

    /**
     * Get authorization URL for OAuth flow.
     *
     * @throws OAuthException
     */
    public function getAuthorizationUrl(string $provider, ?int $userId = null, ?string $redirectUri = null, string $clientIp = '0.0.0.0', string $userAgent = ''): string
    {
        // Check rate limiting
        $this->checkRateLimit($provider, $userId, $clientIp);

        // Get provider configuration
        $providerConfig = $this->providerService->getProvider($provider);
        if (! $providerConfig || ! $providerConfig->isEnabled()) {
            throw OAuthException::configurationError(
                "Provider '{$provider}' is not available",
                $provider
            );
        }

        // Create OAuth client
        $client = OAuthClientFactory::create($providerConfig);

        // Create state parameter for CSRF protection
        $payload = [];
        if ($redirectUri) {
            $payload['redirect_uri'] = $redirectUri;
        }

        $state = $this->stateRepository->createState(
            provider: $provider,
            userId: $userId,
            payload: $payload,
            clientIp: $clientIp,
            userAgent: $userAgent
        );

        // Generate authorization URL
        return $client->getAuthorizationUrl($state->state);
    }

    /**
     * Handle OAuth callback and create/update user binding.
     *
     * @throws OAuthException
     */
    public function handleCallback(string $provider, string $code, string $state, string $clientIp): UserOAuthAccount
    {
        // Validate and consume state parameter
        $stateData = $this->stateRepository->validateAndConsumeState($state, $provider);
        if (! $stateData) {
            throw OAuthException::stateError(
                'Invalid or expired OAuth state parameter',
                $provider,
                ['state' => $state]
            );
        }

        // Get provider configuration
        $providerConfig = $this->providerService->getProvider($provider);
        if (! $providerConfig || ! $providerConfig->isEnabled()) {
            throw OAuthException::configurationError(
                "Provider '{$provider}' is not available",
                $provider
            );
        }

        // Create OAuth client
        $client = OAuthClientFactory::create($providerConfig);

        try {
            // Exchange code for access token
            $tokenData = $client->getAccessToken($code);

            // Get user information
            $userData = $client->getUserInfo($tokenData['access_token'])['user_data'];
            // Find or create user binding
            return $this->createOrUpdateBinding(
                $stateData,
                $tokenData,
                $userData,
                $provider,
                $clientIp
            );
        } catch (\Throwable $e) {
            var_dump($e);
            throw OAuthException::flowError(
                "OAuth callback failed: {$e->getMessage()}",
                $provider,
                ['code' => $code, 'state' => $state]
            );
        }
    }

    /**
     * Handle OAuth login flow - similar to callback but returns JWT tokens for login.
     *
     * @throws OAuthException
     */
    public function handleOAuthLogin(string $provider, string $code, string $state, string $clientIp, string $userAgent = ''): array
    {
        // Validate and consume state parameter
        $stateData = $this->stateRepository->validateAndConsumeState($state, $provider);
        if (! $stateData) {
            throw OAuthException::stateError(
                'Invalid or expired OAuth state parameter',
                $provider,
                ['state' => $state]
            );
        }

        // Get provider configuration
        $providerConfig = $this->providerService->getProvider($provider);
        if (! $providerConfig || ! $providerConfig->isEnabled()) {
            throw OAuthException::configurationError(
                "Provider '{$provider}' is not available",
                $provider
            );
        }

        // Create OAuth client
        $client = OAuthClientFactory::create($providerConfig);

        try {
            // Exchange code for access token
            $tokenData = $client->getAccessToken($code);

            // Get user information
            $userData = $client->getUserInfo($tokenData['access_token']);
            $userInfo = $userData['user_data'] ?? $userData;

            // Look for existing OAuth binding
            $oauthAccount = $this->accountRepository->findByProviderUser($provider, $userInfo['id']);

            if ($oauthAccount) {
                // User exists with OAuth binding - login
                $user = $this->userRepository->findById($oauthAccount->user_id);
                if (! $user) {
                    throw OAuthException::flowError(
                        'User account not found',
                        $provider,
                        ['user_id' => $oauthAccount->user_id]
                    );
                }

                // Update OAuth account info
                $oauthAccount->updateTokens(
                    $tokenData['access_token'],
                    $tokenData['refresh_token'] ?? null,
                    isset($tokenData['expires_in'])
                        ? Carbon::now()->addSeconds($tokenData['expires_in'])
                        : null
                );
                $oauthAccount->updateLoginInfo($clientIp);

                // Generate JWT tokens for the user (direct JWT generation for OAuth)
                $loginResult = $this->generateJwtForUser($user, $clientIp, $userAgent);

                return [
                    'action' => 'login',
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'nickname' => $user->nickname,
                        'avatar' => $user->avatar,
                    ],
                    'oauth_account' => [
                        'id' => $oauthAccount->id,
                        'provider' => $oauthAccount->provider,
                        'provider_user_id' => $oauthAccount->provider_user_id,
                        'provider_username' => $oauthAccount->provider_username,
                        'provider_email' => $oauthAccount->provider_email,
                        'provider_avatar' => $oauthAccount->provider_avatar,
                    ],
                    'tokens' => $loginResult,
                ];
            }

            // No existing binding found - need to register or link account
            throw OAuthException::flowError(
                'No account found for this OAuth provider. Please register first or bind your account.',
                $provider,
                [
                    'provider_user_id' => $userInfo['id'],
                    'provider_username' => $userInfo['username'] ?? $userInfo['name'] ?? '',
                    'provider_email' => $userInfo['email'] ?? '',
                ]
            );
        } catch (OAuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw OAuthException::flowError(
                "OAuth login failed: {$e->getMessage()}",
                $provider,
                ['code' => $code, 'state' => $state]
            );
        }
    }

    /**
     * Get authorization URL for OAuth login (without user binding).
     *
     * @throws OAuthException
     */
    public function getOAuthLoginUrl(string $provider, ?string $redirectUri = null, string $clientIp = '0.0.0.0', string $userAgent = ''): string
    {
        // Check rate limiting
        $this->checkRateLimit($provider, null, $clientIp);

        // Get provider configuration
        $providerConfig = $this->providerService->getProvider($provider);
        if (! $providerConfig || ! $providerConfig->isEnabled()) {
            throw OAuthException::configurationError(
                "Provider '{$provider}' is not available",
                $provider
            );
        }

        // Create OAuth client
        $client = OAuthClientFactory::create($providerConfig);

        // Create state parameter for CSRF protection (no user ID for login flow)
        $payload = ['action' => 'login'];
        if ($redirectUri) {
            $payload['redirect_uri'] = $redirectUri;
        }

        $state = $this->stateRepository->createState(
            provider: $provider,
            userId: null, // No user ID for login flow
            payload: $payload,
            clientIp: $clientIp,
            userAgent: $userAgent
        );

        // Generate authorization URL
        return $client->getAuthorizationUrl($state->state);
    }

    /**
     * Bind OAuth account to user.
     *
     * @throws OAuthException
     */
    public function bindAccount(int $userId, string $provider, array $userData, array $tokenData, string $clientIp): UserOAuthAccount
    {
        // Check if user can bind this provider
        if (! $this->accountRepository->canUserBindProvider($userId, $provider)) {
            throw OAuthException::flowError(
                'User already has an account bound to this provider',
                $provider,
                ['user_id' => $userId]
            );
        }

        // Check if provider account can be bound
        $providerUserId = $userData['id'] ?? '';
        if (! $this->accountRepository->canProviderAccountBeBound($provider, $providerUserId)) {
            throw OAuthException::flowError(
                'This provider account is already bound to another user',
                $provider,
                ['provider_user_id' => $providerUserId]
            );
        }

        // Create binding
        $bindingData = [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_username' => $userData['username'] ?? $userData['name'] ?? null,
            'provider_email' => $userData['email'] ?? null,
            'provider_avatar' => $userData['avatar'] ?? null,
            'provider_data' => $userData['raw'] ?? $userData,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => isset($tokenData['expires_in'])
                ? Carbon::now()->addSeconds($tokenData['expires_in'])
                : null,
        ];

        $binding = $this->accountRepository->createBinding($bindingData);

        // Update login info
        $binding->updateLoginInfo($clientIp);

        return $binding;
    }

    /**
     * Unbind OAuth account from user.
     *
     * @throws OAuthException
     */
    public function unbindAccount(int $userId, string $provider, array $data = []): bool
    {
        $binding = $this->accountRepository->findByUserAndProvider($userId, $provider);
        if (! $binding) {
            throw OAuthException::flowError(
                'No binding found for this provider',
                $provider,
                ['user_id' => $userId]
            );
        }

        return $this->accountRepository->removeBinding($userId, $provider);
    }

    /**
     * Get user's OAuth account bindings.
     */
    public function getUserBindings(int $userId): Collection
    {
        return $this->accountRepository->getUserBindings($userId);
    }

    /**
     * Get user's active OAuth bindings.
     */
    public function getUserActiveBindings(int $userId): Collection
    {
        return $this->accountRepository->getUserActiveBindings($userId);
    }

    /**
     * Refresh OAuth tokens for user and provider.
     *
     * @throws OAuthException
     */
    public function refreshTokens(int $userId, string $provider): bool
    {
        $account = $this->accountRepository->findByUserAndProvider($userId, $provider);
        if (! $account) {
            throw OAuthException::flowError(
                'No binding found for this provider',
                $provider,
                ['user_id' => $userId]
            );
        }

        return $this->refreshAccountTokens($account);
    }

    /**
     * Refresh OAuth tokens for expired accounts.
     */
    public function refreshAccountTokens(UserOAuthAccount $account): bool
    {
        if (! $account->hasRefreshToken()) {
            return false;
        }

        // Get provider configuration
        $providerConfig = $this->providerService->getProvider($account->provider);
        if (! $providerConfig || ! $providerConfig->isEnabled()) {
            return false;
        }

        // Check if provider supports refresh tokens
        if (! $providerConfig->supportsRefreshToken()) {
            return false;
        }

        try {
            // Create OAuth client
            $client = OAuthClientFactory::create($providerConfig);

            // Refresh tokens
            $tokenData = $client->refreshToken($account->refresh_token);

            // Update account with new tokens
            $account->updateTokens(
                $tokenData['access_token'],
                $tokenData['refresh_token'] ?? $account->refresh_token,
                isset($tokenData['expires_in'])
                    ? Carbon::now()->addSeconds($tokenData['expires_in'])
                    : null
            );

            return true;
        } catch (\Throwable $e) {
            // Log refresh failure but don't throw exception
            // TODO: Add logging
            return false;
        }
    }

    /**
     * Batch refresh expired tokens.
     */
    public function refreshExpiredTokens(int $limit = 50): array
    {
        $accounts = $this->accountRepository->getAccountsWithExpiredTokens($limit);
        $results = [];

        foreach ($accounts as $account) {
            $success = $this->refreshAccountTokens($account);
            $results[] = [
                'id' => $account->id,
                'provider' => $account->provider,
                'user_id' => $account->user_id,
                'success' => $success,
            ];
        }

        return $results;
    }

    /**
     * Get accounts that need token refresh.
     */
    public function getAccountsNeedingRefresh(int $hoursBeforeExpiry = 1): Collection
    {
        return $this->accountRepository->getBindingsNeedingRefresh($hoursBeforeExpiry);
    }

    /**
     * Get OAuth flow statistics.
     */
    public function getOAuthStats(): array
    {
        $providerStats = $this->providerService->getSystemStats();
        $bindingStats = $this->accountRepository->getBindingsStats();
        $stateStats = $this->stateRepository->getStatesStats();

        return [
            'providers' => $providerStats,
            'bindings' => $bindingStats,
            'states' => $stateStats,
            'summary' => [
                'total_providers' => $providerStats['total_providers'],
                'enabled_providers' => $providerStats['enabled_providers'],
                'total_bindings' => $bindingStats['total_bindings'],
                'active_bindings' => $bindingStats['active_bindings'],
                'expired_tokens' => $bindingStats['expired_tokens'],
                'valid_states' => $stateStats['valid'],
                'cleanup_needed' => $stateStats['cleanup_needed'],
            ],
        ];
    }

    /**
     * Perform OAuth system maintenance.
     */
    public function performMaintenance(): array
    {
        $results = [];

        // Cleanup expired states
        $results['states'] = $this->stateRepository->performMaintenance();

        // Refresh tokens that are about to expire
        $results['token_refresh'] = $this->refreshExpiredTokens();

        // Cleanup old inactive bindings
        $results['binding_cleanup'] = $this->accountRepository->cleanupOldInactiveBindings();

        $results['maintenance_completed_at'] = Carbon::now()->toISOString();

        return $results;
    }

    /**
     * Get list of all user bindings with pagination and filtering.
     * Returns data in MineAdmin ma-pro-table compatible format.
     */
    public function listAllUserBindings(array $filters = [], int $page = 1, int $pageSize = 15): array
    {
        $query = $this->accountRepository->getModel()->newQuery();

        // Include user relationship for table display
        $query->with(['user:id,username,email,avatar']);

        // Apply filters
        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['username'])) {
            $query->whereHas('user', static function ($userQuery) use ($filters) {
                $userQuery->where('username', 'like', '%' . $filters['username'] . '%');
            });
        }
        if (! empty($filters['provider_username'])) {
            $query->where('provider_username', 'like', '%' . $filters['provider_username'] . '%');
        }
        if (! empty($filters['provider_email'])) {
            $query->where('provider_email', 'like', '%' . $filters['provider_email'] . '%');
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Add sorting
        $query->orderBy('created_at', 'desc');

        // Apply pagination
        $offset = ($page - 1) * $pageSize;
        $total = $query->count();
        $bindings = $query->skip($offset)->take($pageSize)->get();

        // Return in MineAdmin standard format
        return [
            'list' => $bindings->toArray(),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => (int) ceil($total / $pageSize),
        ];
    }

    /**
     * Force unbind account (admin operation).
     *
     * @throws OAuthException
     */
    public function forceUnbindAccount(int $bindingId, ?string $reason = null): bool
    {
        $binding = $this->accountRepository->getModel()->find($bindingId);
        if (! $binding) {
            throw OAuthException::flowError(
                'Binding not found',
                'unknown',
                ['binding_id' => $bindingId]
            );
        }

        // Log the admin action if reason provided
        if ($reason) {
            // TODO: Add admin action logging
        }

        return $this->accountRepository->removeBinding($binding->user_id, $binding->provider);
    }

    /**
     * Get binding statistics for a given period.
     */
    public function getBindingStatistics(string $period = '30d'): array
    {
        $startDate = match ($period) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            '1y' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(30),
        };

        $query = $this->accountRepository->getModel()->newQuery()
            ->where('created_at', '>=', $startDate);

        return [
            'period' => $period,
            'start_date' => $startDate->toISOString(),
            'total_bindings' => $query->count(),
            'active_bindings' => (clone $query)->where('status', 'active')->count(),
            'inactive_bindings' => (clone $query)->where('status', 'inactive')->count(),
            'by_provider' => (clone $query)
                ->select('provider')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('provider')
                ->get()
                ->pluck('count', 'provider')
                ->toArray(),
            'daily_stats' => (clone $query)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
                ->toArray(),
        ];
    }

    /**
     * Batch operate on multiple bindings.
     *
     * @throws OAuthException
     */
    public function batchOperateBindings(string $action, array $bindingIds, ?string $reason = null): array
    {
        $results = [];
        $bindings = $this->accountRepository->getModel()
            ->whereIn('id', $bindingIds)
            ->get();

        if ($bindings->count() !== \count($bindingIds)) {
            throw OAuthException::flowError(
                'Some binding IDs were not found',
                'unknown',
                ['provided_ids' => $bindingIds, 'found_count' => $bindings->count()]
            );
        }

        foreach ($bindings as $binding) {
            try {
                $success = match ($action) {
                    'unbind' => $this->accountRepository->removeBinding($binding->user_id, $binding->provider),
                    'activate' => $this->updateBindingStatus($binding->id, 'active'),
                    'deactivate' => $this->updateBindingStatus($binding->id, 'inactive'),
                    default => throw OAuthException::flowError("Unknown action: {$action}", 'unknown'),
                };

                $results[] = [
                    'id' => $binding->id,
                    'user_id' => $binding->user_id,
                    'provider' => $binding->provider,
                    'action' => $action,
                    'success' => $success,
                    'reason' => $reason,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'id' => $binding->id,
                    'user_id' => $binding->user_id,
                    'provider' => $binding->provider,
                    'action' => $action,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'reason' => $reason,
                ];
            }
        }

        return $results;
    }

    /**
     * Export bindings data in specified format.
     *
     * @throws OAuthException
     */
    public function exportBindings(array $filters = [], string $format = 'csv'): array
    {
        if (! \in_array($format, ['csv', 'excel', 'json'], true)) {
            throw OAuthException::flowError(
                "Unsupported export format: {$format}",
                'unknown',
                ['supported_formats' => ['csv', 'excel', 'json']]
            );
        }

        // Get filtered data (no pagination for export)
        $query = $this->accountRepository->getModel()->newQuery();

        // Apply same filters as listAllUserBindings
        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['provider_email'])) {
            $query->where('provider_email', 'like', '%' . $filters['provider_email'] . '%');
        }
        if (! empty($filters['created_at_start'])) {
            $query->where('created_at', '>=', $filters['created_at_start']);
        }
        if (! empty($filters['created_at_end'])) {
            $query->where('created_at', '<=', $filters['created_at_end']);
        }

        $bindings = $query->orderBy('created_at', 'desc')->get();

        // Transform data for export
        $exportData = $bindings->map(static function ($binding) {
            return [
                'id' => $binding->id,
                'user_id' => $binding->user_id,
                'provider' => $binding->provider,
                'provider_user_id' => $binding->provider_user_id,
                'provider_username' => $binding->provider_username,
                'provider_email' => $binding->provider_email,
                'status' => $binding->status->value ?? $binding->status,
                'created_at' => $binding->created_at?->toISOString(),
                'updated_at' => $binding->updated_at?->toISOString(),
                'last_login_at' => $binding->last_login_at?->toISOString(),
                'token_expires_at' => $binding->token_expires_at?->toISOString(),
            ];
        })->toArray();

        return [
            'data' => $exportData,
            'format' => $format,
            'total_records' => \count($exportData),
            'exported_at' => Carbon::now()->toISOString(),
            'filters_applied' => $filters,
        ];
    }

    /**
     * Get list of available OAuth providers.
     */
    public function getAvailableProviders(): array
    {
        return $this->providerService->getEnabledProviders()
            ->map(static function (OAuthProvider $provider) {
                return [
                    'name' => $provider->name,
                    'display_name' => $provider->display_name,
                    'icon' => $provider->getIcon(),
                    'description' => $provider->remark,
                    'enabled' => $provider->isEnabled(),
                    'supports_refresh' => $provider->supportsRefreshToken(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get list of available OAuth providers for a specific user.
     */
    public function getAvailableProvidersForUser(int $userId): array
    {
        // Get user's current bindings
        $userBindings = $this->accountRepository->getUserBindings($userId);
        $boundProviders = $userBindings->pluck('provider')->toArray();

        return $this->providerService->getEnabledProviders()
            ->map(static function (OAuthProvider $provider) use ($boundProviders) {
                return [
                    'name' => $provider->name,
                    'display_name' => $provider->display_name,
                    'icon' => $provider->getIcon(),
                    'description' => $provider->remark,
                    'enabled' => $provider->isEnabled(),
                    'supports_refresh' => $provider->supportsRefreshToken(),
                    'is_bound' => \in_array($provider->name, $boundProviders, true),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Check OAuth flow rate limiting.
     *
     * @throws OAuthException
     */
    private function checkRateLimit(string $provider, ?int $userId = null, string $clientIp = ''): void
    {
        // Check user rate limit
        if ($userId && $this->stateRepository->hasUserExceededRateLimit($userId, $provider)) {
            throw OAuthException::rateLimitError(
                'User has exceeded OAuth request rate limit',
                $provider
            );
        }

        // Check IP rate limit
        if ($clientIp && $this->stateRepository->hasIpExceededRateLimit($clientIp, $provider)) {
            throw OAuthException::rateLimitError(
                'IP address has exceeded OAuth request rate limit',
                $provider
            );
        }
    }

    /**
     * Create or update user binding from callback data.
     *
     * @throws OAuthException
     */
    private function createOrUpdateBinding(
        array $stateData,
        array $tokenData,
        array $userData,
        string $provider,
        string $clientIp
    ): UserOAuthAccount {
        $userId = $stateData['user_id'];
        $providerUserId = $userData['id'] ?? '';

        if (empty($providerUserId)) {
            throw OAuthException::flowError(
                'Provider did not return user ID',
                $provider,
                ['user_data' => $userData]
            );
        }

        // Check if user already has a binding for this provider
        $existingBinding = null;
        if ($userId) {
            $existingBinding = $this->accountRepository->findByUserAndProvider($userId, $provider);
        }

        // Check if provider account is already bound to someone else
        $providerAccountBinding = $this->accountRepository->findByProviderUser($provider, $providerUserId);

        if ($providerAccountBinding && (! $userId || $providerAccountBinding->user_id !== $userId)) {
            throw OAuthException::flowError(
                'This provider account is already bound to another user',
                $provider,
                [
                    'provider_user_id' => $providerUserId,
                    'bound_to_user' => $providerAccountBinding->user_id,
                    'current_user' => $userId,
                ]
            );
        }

        $bindingData = [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_username' => $userData['username'] ?? $userData['name'] ?? null,
            'provider_email' => $userData['email'] ?? null,
            'provider_avatar' => $userData['avatar'] ?? null,
            'provider_data' => $userData['raw'] ?? $userData,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => isset($tokenData['expires_in'])
                ? Carbon::now()->addSeconds($tokenData['expires_in'])
                : null,
        ];

        if ($existingBinding) {
            // Update existing binding
            foreach ($bindingData as $key => $value) {
                if ($key !== 'user_id' && $key !== 'provider') {
                    $existingBinding->{$key} = $value;
                }
            }
            $existingBinding->save();
            $binding = $existingBinding;
        } else {
            // Create new binding
            $binding = $this->accountRepository->createBinding($bindingData);
        }

        // Update login info
        $binding->updateLoginInfo($clientIp);

        return $binding;
    }

    /**
     * Update binding status (helper method for batch operations).
     */
    private function updateBindingStatus(int $bindingId, string $status): bool
    {
        $binding = $this->accountRepository->getModel()->find($bindingId);
        if (! $binding) {
            return false;
        }

        $binding->status = $status;
        return $binding->save();
    }

    /**
     * Generate JWT tokens for user (for OAuth login).
     */
    private function generateJwtForUser(User $user, string $clientIp, string $userAgent): array
    {
        // Check if user is enabled
        if ($user->status->isDisable()) {
            throw OAuthException::flowError(
                'User account is disabled',
                'oauth',
                ['user_id' => $user->id]
            );
        }

        // Dispatch login event
        $this->dispatcher->dispatch(new UserLoginEvent($user, $clientIp, $userAgent, $userAgent));

        // Generate JWT tokens
        $jwt = $this->jwtFactory->get('default');
        return [
            'access_token' => $jwt->builderAccessToken((string) $user->id)->toString(),
            'refresh_token' => $jwt->builderRefreshToken((string) $user->id)->toString(),
            'expire_at' => (int) $jwt->getConfig('ttl', 0),
        ];
    }
}
