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

use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;
use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;

/**
 * QQ OAuth2 Client for platform integration.
 *
 * @see https://wiki.connect.qq.com/oauth2-0%E7%AE%80%E4BB%8B
 */
class QQOAuthClient extends AbstractOAuthClient
{
    /**
     * QQ OAuth2 authorization endpoint.
     * Verified correct as of 2024 - official QQ Connect graph API.
     */
    protected const AUTHORIZE_URL = 'https://graph.qq.com/oauth2.0/authorize';

    /**
     * QQ token endpoint.
     * Verified correct as of 2024 - official QQ Connect graph API.
     */
    protected const TOKEN_URL = 'https://graph.qq.com/oauth2.0/token';

    /**
     * QQ OpenID endpoint.
     * Verified correct as of 2024 - official QQ Connect graph API.
     */
    protected const OPENID_URL = 'https://graph.qq.com/oauth2.0/me';

    /**
     * QQ user info endpoint.
     * Verified correct as of 2024 - official QQ Connect user API.
     */
    protected const USER_INFO_URL = 'https://graph.qq.com/user/get_user_info';

    /**
     * Get QQ authorization URL.
     *
     * @param string $state CSRF state parameter
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->normalizeScopesToString(),
            'state' => $state,
        ];

        $url = self::AUTHORIZE_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Generated QQ authorization URL', [
            'url' => $url,
            'scopes' => $this->scopes,
        ]);

        return $url;
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param string $code Authorization code from callback
     * @return array token response with access_token, etc
     * @throws OAuthException
     */
    public function getAccessToken(string $code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ];

        $url = self::TOKEN_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Requesting QQ access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->makeQQRequest($url);

        $tokenData = $this->validateQQTokenResponse($response);

        $this->logActivity('Successfully obtained QQ access token', [
            'expires_in' => $tokenData['expires_in'],
        ]);

        return $tokenData;
    }

    /**
     * Get user information using access token.
     *
     * @param string $accessToken Access token
     * @return array Complete user info with standardized format
     * @throws OAuthException
     */
    public function getUserInfo(string $accessToken): array
    {
        // Step 1: Get OpenID using access token
        $openIdParams = [
            'access_token' => $accessToken,
        ];

        $openIdUrl = self::OPENID_URL . '?' . $this->buildQueryString($openIdParams);

        $this->logActivity('Requesting QQ OpenID');

        $openIdResponse = $this->makeQQRequest($openIdUrl);

        if (! isset($openIdResponse['openid'])) {
            throw new OAuthException('Missing openid in QQ response');
        }

        $openid = $openIdResponse['openid'];

        // Step 2: Get user info using access token and openid
        $userInfoParams = [
            'access_token' => $accessToken,
            'oauth_consumer_key' => $this->clientId,
            'openid' => $openid,
        ];

        $userInfoUrl = self::USER_INFO_URL . '?' . $this->buildQueryString($userInfoParams);

        $this->logActivity('Requesting QQ user info', [
            'openid' => $openid,
        ]);

        $userData = $this->makeQQRequest($userInfoUrl);

        // Check for errors in user info response
        if (isset($userData['ret']) && $userData['ret'] !== 0) {
            throw new OAuthException(
                "QQ user info error: {$userData['msg']} (ret: {$userData['ret']})"
            );
        }

        // Add openid to user data for ID extraction
        $userData['openid'] = $openid;

        $parsedUserData = $this->parseUserData($userData);

        $this->logActivity('Successfully retrieved QQ user info', [
            'user_id' => $parsedUserData['id'],
            'username' => $parsedUserData['username'],
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => null, // QQ doesn't provide refresh tokens in standard flow
            'token_type' => 'Bearer',
            'expires_in' => null, // Will be set by caller if available
            'user_data' => $parsedUserData,
        ];
    }

    /**
     * Refresh access token using refresh token.
     * Note: QQ doesn't support refresh tokens in the standard OAuth flow.
     *
     * @param string $refreshToken Refresh token
     * @return array New token response
     * @throws OAuthException
     */
    public function refreshToken(string $refreshToken): array
    {
        throw new OAuthException('QQ does not support refresh tokens in standard OAuth flow');
    }

    /**
     * Check if provider supports refresh tokens.
     * QQ doesn't support refresh tokens in standard flow.
     */
    public function supportsRefreshToken(): bool
    {
        return false;
    }

    /**
     * Extract user ID from QQ user data.
     *
     * @param array $userData Raw user data from QQ
     * @return string User ID (openid)
     */
    protected function getUserId(array $userData): string
    {
        return $userData['openid'] ?? throw new OAuthException('Missing openid in QQ response');
    }

    /**
     * Extract username from QQ user data.
     *
     * @param array $userData Raw user data from QQ
     * @return null|string Username (QQ uses nickname)
     */
    protected function getUsername(array $userData): ?string
    {
        return $userData['nickname'] ?? null;
    }

    /**
     * Extract display name from QQ user data.
     *
     * @param array $userData Raw user data from QQ
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['nickname'] ?? null;
    }

    /**
     * Extract email from QQ user data.
     * Note: QQ doesn't provide email through OAuth.
     *
     * @param array $userData Raw user data from QQ
     * @return null|string Email address (always null for QQ)
     */
    protected function getEmail(array $userData): ?string
    {
        // QQ doesn't provide email through OAuth
        return null;
    }

    /**
     * Extract avatar URL from QQ user data.
     *
     * @param array $userData Raw user data from QQ
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        // QQ provides multiple avatar sizes, prefer larger ones
        return $userData['figureurl_qq_2']
            ?? $userData['figureurl_qq_1']
            ?? $userData['figureurl_2']
            ?? $userData['figureurl_1']
            ?? $userData['figureurl']
            ?? null;
    }

    /**
     * Make HTTP request to QQ API and handle JSONP responses.
     *
     * @param string $url Request URL
     * @return array Parsed response data
     * @throws OAuthException
     */
    protected function makeQQRequest(string $url): array
    {
        try {
            $client = ApplicationContext::getContainer()
                ->get(ClientFactory::class)
                ->create([
                    'timeout' => 30,
                    'verify' => false,
                ]);

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'MineAdmin OAuth2 Client/1.0',
                    'Accept' => '*/*',
                ],
            ]);

            $body = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new OAuthException("HTTP request failed with status {$statusCode}: {$body}");
            }

            // Handle JSONP callback response (QQ returns callback(...))
            if (str_starts_with(trim($body), 'callback(')) {
                $body = preg_replace('/^callback\s*\(\s*|\s*\)\s*;?\s*$/', '', $body);
            }

            $data = json_decode($body, true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                // Try to parse as query string (some QQ endpoints return form-encoded data)
                parse_str($body, $data);
                if (empty($data)) {
                    throw new OAuthException('Invalid response format from QQ: ' . json_last_error_msg());
                }
            }

            return $data;
        } catch (\Throwable $e) {
            if ($e instanceof OAuthException) {
                throw $e;
            }
            throw new OAuthException("QQ API request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Validate QQ token response.
     *
     * @param array $response Token response from QQ
     * @return array Validated token data
     * @throws OAuthException
     */
    protected function validateQQTokenResponse(array $response): array
    {
        if (isset($response['error'])) {
            throw new OAuthException(
                "QQ OAuth error: {$response['error_description']} (error: {$response['error']})"
            );
        }

        if (! isset($response['access_token'])) {
            throw new OAuthException('Missing access_token in QQ response');
        }

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'token_type' => 'Bearer',
            'expires_in' => isset($response['expires_in']) ? (int) $response['expires_in'] : null,
            'scope' => $response['scope'] ?? null,
        ];
    }
}
