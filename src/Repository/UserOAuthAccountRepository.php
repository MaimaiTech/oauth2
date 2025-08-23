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

namespace Plugin\MaimaiTech\OAuth2\Repository;

use App\Repository\IRepository;
use Carbon\Carbon;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthStatusEnum;
use Plugin\MaimaiTech\OAuth2\Model\UserOAuthAccount;

use function Hyperf\Support\now;

/**
 * @extends IRepository<UserOAuthAccount>
 */
final class UserOAuthAccountRepository extends IRepository
{
    public function __construct(
        protected readonly UserOAuthAccount $model
    ) {}

    /**
     * Build query for active accounts.
     */
    public function queryActiveAccounts(): Builder
    {
        return $this->model->newQuery()->where('status', OAuthStatusEnum::NORMAL);
    }

    /**
     * Build query filtered by provider.
     */
    public function queryByProvider(string $provider): Builder
    {
        return $this->model->newQuery()->where('provider', $provider);
    }

    /**
     * Build query filtered by user.
     */
    public function queryByUser(int $userId): Builder
    {
        return $this->model->newQuery()->where('user_id', $userId);
    }

    /**
     * Build query for accounts with valid tokens.
     */
    public function queryWithValidTokens(): Builder
    {
        return $this->model->newQuery()
            ->where('access_token', '!=', '')
            ->whereNotNull('access_token')
            ->where(static function ($q) {
                $q->whereNull('token_expires_at')
                    ->orWhere('token_expires_at', '>', now());
            });
    }

    /**
     * Build query for accounts with expired tokens.
     */
    public function queryWithExpiredTokens(): Builder
    {
        return $this->model->newQuery()
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now());
    }

    /**
     * Find account binding by user and provider.
     */
    public function findByUserAndProvider(int $userId, string $provider): ?UserOAuthAccount
    {
        return $this->queryByUser($userId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Find account binding by provider and provider user ID.
     */
    public function findByProviderUser(string $provider, string $providerUserId): ?UserOAuthAccount
    {
        return $this->model->newQuery()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    /**
     * Get user's OAuth account bindings.
     */
    public function getUserBindings(int $userId): Collection
    {
        return $this->queryByUser($userId)
            ->with('oauthProvider')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active user bindings.
     */
    public function getUserActiveBindings(int $userId): Collection
    {
        return $this->queryByUser($userId)
            ->where('status', OAuthStatusEnum::NORMAL)
            ->with('oauthProvider')
            ->orderBy('last_login_at', 'desc')
            ->get();
    }

    /**
     * Create OAuth account binding.
     */
    public function createBinding(array $data): UserOAuthAccount
    {
        // Ensure required fields are present
        $requiredFields = ['user_id', 'provider', 'provider_user_id'];
        foreach ($requiredFields as $field) {
            if (! isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Check for existing binding
        $existing = $this->findByUserAndProvider($data['user_id'], $data['provider']);
        if ($existing) {
            throw new \RuntimeException("User already has a binding for provider: {$data['provider']}");
        }

        // Check if provider account is already bound to another user
        $existingProviderAccount = $this->findByProviderUser($data['provider'], $data['provider_user_id']);
        if ($existingProviderAccount) {
            throw new \RuntimeException('Provider account is already bound to another user');
        }

        return $this->create($data);
    }

    /**
     * Update OAuth tokens for an account.
     */
    public function updateTokens(int $id, array $tokens): bool
    {
        $data = [];

        if (isset($tokens['access_token'])) {
            $data['access_token'] = $tokens['access_token'];
        }

        if (isset($tokens['refresh_token'])) {
            $data['refresh_token'] = $tokens['refresh_token'];
        }

        if (isset($tokens['expires_at'])) {
            $data['token_expires_at'] = $tokens['expires_at'] instanceof Carbon
                ? $tokens['expires_at']
                : Carbon::parse($tokens['expires_at']);
        }

        return $this->updateById($id, $data);
    }

    /**
     * Update login information for an account.
     */
    public function updateLoginInfo(int $id, ?string $ip = null): bool
    {
        return $this->updateById($id, [
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Remove OAuth account binding.
     */
    public function removeBinding(int $userId, string $provider): bool
    {
        $binding = $this->findByUserAndProvider($userId, $provider);

        if (! $binding) {
            return false;
        }

        return (bool) $this->deleteById($binding->id);
    }

    /**
     * Force remove binding by ID (admin operation).
     */
    public function forceRemoveBinding(int $id): bool
    {
        return (bool) $this->deleteById($id);
    }

    /**
     * Get accounts with expired tokens.
     */
    public function getAccountsWithExpiredTokens(int $limit = 100): Collection
    {
        return $this->model->newQuery()
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now())
            ->where('status', OAuthStatusEnum::NORMAL)
            ->whereNotNull('refresh_token')
            ->with('oauthProvider')
            ->limit($limit)
            ->get();
    }

    /**
     * Get accounts by provider.
     */
    public function getAccountsByProvider(string $provider): Collection
    {
        return $this->queryByProvider($provider)
            ->with(['user', 'oauthProvider'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get binding statistics by provider.
     */
    public function getBindingStatsByProvider(): Collection
    {
        return $this->model->newQuery()
            ->selectRaw('provider, COUNT(*) as total_bindings')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_bindings', [OAuthStatusEnum::NORMAL->value])
            ->groupBy('provider')
            ->orderBy('total_bindings', 'desc')
            ->get()
            ->map(static function ($stat) {
                $stat->inactive_bindings = $stat->total_bindings - $stat->active_bindings;
                return $stat;
            });
    }

    /**
     * Get recent login activities.
     */
    public function getRecentLoginActivities(int $limit = 20): Collection
    {
        return $this->model->newQuery()
            ->whereNotNull('last_login_at')
            ->with(['user:id,username,nickname', 'oauthProvider:name,display_name'])
            ->orderBy('last_login_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get bindings that need token refresh.
     */
    public function getBindingsNeedingRefresh(int $hoursBeforeExpiry = 1): Collection
    {
        $refreshThreshold = now()->addHours($hoursBeforeExpiry);

        return $this->model->newQuery()
            ->where('status', OAuthStatusEnum::NORMAL)
            ->whereNotNull('access_token')
            ->whereNotNull('refresh_token')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', $refreshThreshold)
            ->where('token_expires_at', '>', now())
            ->with('oauthProvider')
            ->get();
    }

    /**
     * Update provider user data.
     */
    public function updateProviderData(int $id, array $userData): bool
    {
        $data = [
            'provider_username' => $userData['username'] ?? $userData['name'] ?? null,
            'provider_email' => $userData['email'] ?? null,
            'provider_avatar' => $userData['avatar'] ?? $userData['avatar_url'] ?? null,
            'provider_data' => $userData,
        ];

        return $this->updateById($id, $data);
    }

    /**
     * Check if user can bind provider (doesn't have existing binding).
     */
    public function canUserBindProvider(int $userId, string $provider): bool
    {
        return ! $this->queryByUser($userId)
            ->where('provider', $provider)
            ->exists();
    }

    /**
     * Check if provider account can be bound (not already bound).
     */
    public function canProviderAccountBeBound(string $provider, string $providerUserId, ?int $excludeUserId = null): bool
    {
        $query = $this->model->newQuery()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId);

        if ($excludeUserId) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        return ! $query->exists();
    }

    /**
     * Handle search parameters for filtering.
     */
    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'user_id'), static function (Builder $query, $userId) {
                if (\is_array($userId)) {
                    $query->whereIn('user_id', $userId);
                } else {
                    $query->where('user_id', $userId);
                }
            })
            ->when(Arr::get($params, 'provider'), static function (Builder $query, $provider) {
                if (\is_array($provider)) {
                    $query->whereIn('provider', $provider);
                } else {
                    $query->where('provider', $provider);
                }
            })
            ->when(Arr::get($params, 'provider_user_id'), static function (Builder $query, $providerUserId) {
                $query->where('provider_user_id', 'like', "%{$providerUserId}%");
            })
            ->when(Arr::get($params, 'provider_username'), static function (Builder $query, $providerUsername) {
                $query->where('provider_username', 'like', "%{$providerUsername}%");
            })
            ->when(Arr::get($params, 'provider_email'), static function (Builder $query, $providerEmail) {
                $query->where('provider_email', 'like', "%{$providerEmail}%");
            })
            ->when(Arr::get($params, 'status'), static function (Builder $query, $status) {
                if (\is_array($status)) {
                    $query->whereIn('status', $status);
                } else {
                    $query->where('status', $status);
                }
            })
            ->when(Arr::get($params, 'has_valid_token'), static function (Builder $query, $hasValidToken) {
                if ($hasValidToken) {
                    $query->where('access_token', '!=', '')
                        ->whereNotNull('access_token')
                        ->where(static function ($q) {
                            $q->whereNull('token_expires_at')
                                ->orWhere('token_expires_at', '>', now());
                        });
                } else {
                    $query->whereNotNull('token_expires_at')
                        ->where('token_expires_at', '<', now());
                }
            })
            ->when(Arr::get($params, 'last_login_at'), static function (Builder $query, $lastLoginAt) {
                if (\is_array($lastLoginAt) && \count($lastLoginAt) === 2) {
                    $query->whereBetween('last_login_at', $lastLoginAt);
                }
            })
            ->when(Arr::get($params, 'created_at'), static function (Builder $query, $createdAt) {
                if (\is_array($createdAt) && \count($createdAt) === 2) {
                    $query->whereBetween('created_at', $createdAt);
                }
            })
            ->when(Arr::get($params, 'with_user'), static function (Builder $query) {
                $query->with('user:id,username,nickname,email');
            })
            ->when(Arr::get($params, 'with_provider'), static function (Builder $query) {
                $query->with('oauthProvider:name,display_name,enabled,status');
            })
            ->when(Arr::get($params, 'sortable'), static function (Builder $query, array $sortable) {
                $query->orderBy(key($sortable), current($sortable));
            }, static function (Builder $query) {
                // Default ordering
                $query->orderBy('created_at', 'desc');
            });
    }

    /**
     * Get binding count by user.
     */
    public function getUserBindingCount(int $userId): int
    {
        return $this->queryByUser($userId)
            ->where('status', OAuthStatusEnum::NORMAL)
            ->count();
    }

    /**
     * Get total bindings count.
     */
    public function getTotalBindingsCount(): int
    {
        return $this->count();
    }

    /**
     * Get active bindings count.
     */
    public function getActiveBindingsCount(): int
    {
        return $this->queryActiveAccounts()->count();
    }

    /**
     * Get bindings summary statistics.
     */
    public function getBindingsStats(): array
    {
        $total = $this->getTotalBindingsCount();
        $active = $this->getActiveBindingsCount();
        $inactive = $total - $active;

        // Expired tokens count
        $expiredTokens = $this->queryWithExpiredTokens()->count();

        // Recent logins (last 7 days)
        $recentLogins = $this->model->newQuery()
            ->where('last_login_at', '>=', now()->subDays(7))
            ->count();

        // Providers with most bindings
        $topProviders = $this->getBindingStatsByProvider()->take(5);

        return [
            'total_bindings' => $total,
            'active_bindings' => $active,
            'inactive_bindings' => $inactive,
            'expired_tokens' => $expiredTokens,
            'recent_logins' => $recentLogins,
            'top_providers' => $topProviders->toArray(),
        ];
    }

    /**
     * Cleanup old inactive bindings.
     */
    public function cleanupOldInactiveBindings(int $daysOld = 90): int
    {
        return $this->model->newQuery()
            ->where('status', OAuthStatusEnum::DISABLED)
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
