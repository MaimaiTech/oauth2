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

namespace Plugin\MaimaiTech\OAuth2\Client;

use Carbon\Carbon;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Logger\LoggerFactory;
use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;

/**
 * Abstract OAuth2 Client for third-party platform integration.
 */
abstract class AbstractOAuthClient
{
    protected OAuthProvider $provider;

    protected string $clientId;

    protected string $clientSecret;

    protected string $redirectUri;

    protected array $scopes;

    protected array $extraConfig;

    public function __construct(OAuthProvider $provider)
    {
        $this->provider = $provider;
        $this->clientId = $provider->client_id;
        $this->clientSecret = $provider->client_secret;
        $this->redirectUri = $provider->redirect_uri;
        $this->scopes = $provider->getEffectiveScopes();
        $this->extraConfig = $provider->extra_config ?? [];

        $this->validateConfiguration();
    }

    /**
     * Get authorization URL for OAuth flow.
     */
    abstract public function getAuthorizationUrl(string $state): string;

    /**
     * Exchange authorization code for access token.
     */
    abstract public function getAccessToken(string $code): array;

    /**
     * Get user information using access token.
     */
    abstract public function getUserInfo(string $accessToken): array;

    /**
     * Refresh access token using refresh token.
     */
    abstract public function refreshToken(string $refreshToken): array;

    /**
     * Get provider display name.
     */
    public function getProviderDisplayName(): string
    {
        return $this->provider->display_name;
    }

    /**
     * Get provider name.
     */
    public function getProviderName(): string
    {
        return $this->provider->name;
    }

    /**
     * Get provider configuration.
     */
    public function getProvider(): OAuthProvider
    {
        return $this->provider;
    }

    /**
     * Check if provider supports refresh tokens.
     */
    public function supportsRefreshToken(): bool
    {
        return $this->provider->supportsRefreshToken();
    }

    /**
     * Get debug information for troubleshooting.
     */
    public function getDebugInfo(): array
    {
        return [
            'provider' => $this->provider->name,
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scopes' => $this->scopes,
            'supports_refresh' => $this->supportsRefreshToken(),
            'extra_config' => array_keys($this->extraConfig),
        ];
    }

    /**
     * Validate OAuth configuration.
     *
     * @throws OAuthException
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->clientId)) {
            throw new OAuthException("Missing client_id for provider: {$this->provider->name}");
        }

        if (empty($this->clientSecret)) {
            throw new OAuthException("Missing client_secret for provider: {$this->provider->name}");
        }

        if (empty($this->redirectUri)) {
            throw new OAuthException("Missing redirect_uri for provider: {$this->provider->name}");
        }
    }

    /**
     * Build query string from parameters.
     */
    protected function buildQueryString(array $params): string
    {
        return http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Make HTTP GET request.
     *
     * @throws OAuthException
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        return $this->makeHttpRequest('GET', $url, null, $headers);
    }

    /**
     * Make HTTP POST request.
     *
     * @throws OAuthException
     */
    protected function httpPost(string $url, array $data = [], array $headers = []): array
    {
        $headers['Content-Type'] ??= 'application/x-www-form-urlencoded';

        $body = str_starts_with($headers['Content-Type'], 'application/json')
            ? json_encode($data)
            : http_build_query($data);

        return $this->makeHttpRequest('POST', $url, $body, $headers);
    }

    /**
     * Make HTTP request using Hyperf's HTTP client.
     *
     * @throws OAuthException
     */
    protected function makeHttpRequest(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        try {
            // Use Hyperf's HTTP client
            $client = ApplicationContext::getContainer()->get(ClientFactory::class)->create([
                'timeout' => 30,
                'verify' => false, // For development, should be true in production
            ]);

            $options = [
                'headers' => array_merge([
                    'User-Agent' => 'MineAdmin OAuth2 Client/1.0',
                    'Accept' => 'application/json',
                ], $headers),
            ];

            if ($body !== null) {
                $options['body'] = $body;
            }

            $response = $client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new OAuthException(
                    "HTTP request failed with status {$statusCode}: {$responseBody}",
                    $statusCode
                );
            }

            $data = json_decode($responseBody, true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                throw new OAuthException('Invalid JSON response: ' . json_last_error_msg());
            }

            return $data;
        } catch (\Throwable $e) {
            if ($e instanceof OAuthException) {
                throw $e;
            }
            throw new OAuthException("HTTP request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Parse user data from provider response.
     */
    protected function parseUserData(array $userData): array
    {
        return [
            'id' => $this->getUserId($userData),
            'username' => $this->getUsername($userData),
            'name' => $this->getDisplayName($userData),
            'email' => $this->getEmail($userData),
            'avatar' => $this->getAvatar($userData),
            'raw' => $userData,
        ];
    }

    /**
     * Extract user ID from provider data.
     */
    abstract protected function getUserId(array $userData): string;

    /**
     * Extract username from provider data.
     */
    abstract protected function getUsername(array $userData): ?string;

    /**
     * Extract display name from provider data.
     */
    abstract protected function getDisplayName(array $userData): ?string;

    /**
     * Extract email from provider data.
     */
    abstract protected function getEmail(array $userData): ?string;

    /**
     * Extract avatar URL from provider data.
     */
    abstract protected function getAvatar(array $userData): ?string;

    /**
     * Handle OAuth errors from provider response.
     *
     * @throws OAuthException
     */
    protected function handleOAuthError(array $response): void
    {
        if (isset($response['error'])) {
            $error = $response['error'];
            $description = $response['error_description'] ?? $response['error_hint'] ?? 'Unknown OAuth error';
            throw new OAuthException("OAuth error: {$error} - {$description}");
        }
    }

    /**
     * Get configuration value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->extraConfig[$key] ?? $default;
    }

    /**
     * Generate PKCE code verifier.
     */
    protected function generateCodeVerifier(): string
    {
        return mb_rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate PKCE code challenge.
     */
    protected function generateCodeChallenge(string $codeVerifier): string
    {
        return mb_rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /**
     * Log OAuth activity for debugging.
     */
    protected function logActivity(string $message, array $context = []): void
    {
        // TODO: Implement logging using MineAdmin's logging system
        // For now, this is a placeholder for future logging implementation
        if (config('app.debug', false)) {
            ApplicationContext::getContainer()->get(LoggerFactory::class)
                ->get('oauth2')->info($message, array_merge($context, [
                    'provider' => $this->provider->name,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]));
        }
    }

    /**
     * Validate token response.
     *
     * @throws OAuthException
     */
    protected function validateTokenResponse(array $response): array
    {
        $this->handleOAuthError($response);

        if (! isset($response['access_token'])) {
            throw new OAuthException('Missing access_token in OAuth response');
        }

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'token_type' => $response['token_type'] ?? 'Bearer',
            'expires_in' => isset($response['expires_in']) ? (int) $response['expires_in'] : null,
            'scope' => $response['scope'] ?? null,
        ];
    }

    /**
     * Calculate token expiration timestamp.
     */
    protected function calculateTokenExpiration(?int $expiresIn): ?Carbon
    {
        if ($expiresIn === null) {
            return null;
        }

        return Carbon::now()->addSeconds($expiresIn);
    }

    /**
     * Normalize scopes array to string.
     */
    protected function normalizeScopesToString(): string
    {
        return implode(' ', $this->scopes);
    }

    /**
     * Check if response indicates an error.
     */
    protected function hasError(array $response): bool
    {
        return isset($response['error']) || isset($response['error_code']);
    }
}
