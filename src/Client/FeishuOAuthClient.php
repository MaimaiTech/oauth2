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

use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;

/**
 * Feishu (Lark) OAuth2 Client for platform integration.
 *
 * @see https://open.feishu.cn/document/server-docs/authentication-management/authentication/authentication
 */
class FeishuOAuthClient extends AbstractOAuthClient
{
    /**
     * Feishu OAuth2 authorization endpoint.
     * Updated to use accounts.feishu.cn domain as specified in official documentation.
     */
    protected const AUTHORIZE_URL = 'https://accounts.feishu.cn/open-apis/authen/v1/authorize';

    /**
     * Feishu token endpoint.
     * Updated to use v2 endpoint as required by Feishu OAuth 2.0 specification.
     */
    protected const TOKEN_URL = 'https://open.feishu.cn/open-apis/authen/v2/oauth/token';

    /**
     * Feishu user info endpoint.
     * Uses v1 endpoint which is still the current standard for user info retrieval.
     */
    protected const USER_INFO_URL = 'https://open.feishu.cn/open-apis/authen/v1/user_info';

    /**
     * Get Feishu authorization URL.
     *
     * @param string $state CSRF state parameter
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => $this->normalizeScopesToString(),
            'state' => $state,
        ];

        $url = self::AUTHORIZE_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Generated Feishu authorization URL', [
            'url' => $url,
            'scopes' => $this->scopes,
        ]);

        return $url;
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param string $code Authorization code from callback
     * @return array token response with access_token, refresh_token, etc
     * @throws OAuthException
     */
    public function getAccessToken(string $code): array
    {
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ];

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];

        $this->logActivity('Requesting Feishu access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        // Handle Feishu-specific error format
        if (isset($response['code']) && $response['code'] !== 0) {
            throw new OAuthException(
                "Feishu OAuth error: {$response['msg']} (code: {$response['code']})"
            );
        }

        $tokenData = $this->validateFeishuTokenResponse($response);

        $this->logActivity('Successfully obtained Feishu access token', [
            'expires_in' => $tokenData['expires_in'],
            'has_refresh_token' => ! empty($tokenData['refresh_token']),
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
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        $this->logActivity('Requesting Feishu user info');

        $response = $this->httpGet(self::USER_INFO_URL, $headers);

        // Handle Feishu-specific error format
        if (isset($response['code']) && $response['code'] !== 0) {
            throw new OAuthException(
                "Feishu user info error: {$response['msg']} (code: {$response['code']})"
            );
        }

        $userData = $response['data'] ?? $response;
        $parsedUserData = $this->parseUserData($userData);

        $this->logActivity('Successfully retrieved Feishu user info', [
            'user_id' => $parsedUserData['id'],
            'username' => $parsedUserData['username'],
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => null, // Will be set by caller if available
            'token_type' => 'Bearer',
            'expires_in' => null, // Will be set by caller if available
            'user_data' => $parsedUserData,
        ];
    }

    /**
     * Refresh access token using refresh token.
     *
     * @param string $refreshToken Refresh token
     * @return array New token response
     * @throws OAuthException
     */
    public function refreshToken(string $refreshToken): array
    {
        $data = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ];

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];

        $this->logActivity('Refreshing Feishu access token');

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        // Handle Feishu-specific error format
        if (isset($response['code']) && $response['code'] !== 0) {
            throw new OAuthException(
                "Feishu token refresh error: {$response['msg']} (code: {$response['code']})"
            );
        }

        $tokenData = $this->validateFeishuTokenResponse($response);

        $this->logActivity('Successfully refreshed Feishu access token', [
            'expires_in' => $tokenData['expires_in'],
        ]);

        return $tokenData;
    }

    /**
     * Extract user ID from Feishu user data.
     *
     * @param array $userData Raw user data from Feishu
     * @return string User ID (union_id preferred)
     */
    protected function getUserId(array $userData): string
    {
        return $userData['union_id'] ?? $userData['user_id'] ?? $userData['open_id'] ?? throw new OAuthException('Missing user ID in Feishu response');
    }

    /**
     * Extract username from Feishu user data.
     *
     * @param array $userData Raw user data from Feishu
     * @return null|string Username
     */
    protected function getUsername(array $userData): ?string
    {
        return $userData['name'] ?? $userData['en_name'] ?? null;
    }

    /**
     * Extract display name from Feishu user data.
     *
     * @param array $userData Raw user data from Feishu
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['name'] ?? $userData['en_name'] ?? null;
    }

    /**
     * Extract email from Feishu user data.
     *
     * @param array $userData Raw user data from Feishu
     * @return null|string Email address
     */
    protected function getEmail(array $userData): ?string
    {
        return $userData['email'] ?? null;
    }

    /**
     * Extract avatar URL from Feishu user data.
     *
     * @param array $userData Raw user data from Feishu
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        return $userData['picture'] ?? $userData['avatar_url'] ?? null;
    }

    /**
     * Validate Feishu token response.
     *
     * @param array $response Token response from Feishu
     * @return array Validated token data
     * @throws OAuthException
     */
    protected function validateFeishuTokenResponse(array $response): array
    {
        if (isset($response['code']) && $response['code'] !== 0) {
            throw new OAuthException(
                "Feishu token error: {$response['msg']} (code: {$response['code']})"
            );
        }

        $data = $response['data'] ?? $response;

        if (! isset($data['access_token'])) {
            throw new OAuthException('Missing access_token in Feishu response');
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_type' => 'Bearer',
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            'scope' => $data['scope'] ?? null,
        ];
    }
}
