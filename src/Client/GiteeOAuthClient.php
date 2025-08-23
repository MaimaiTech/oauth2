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
 * Gitee OAuth2 Client for platform integration.
 *
 * @see https://gitee.com/api/v5/oauth_doc#/
 */
class GiteeOAuthClient extends AbstractOAuthClient
{
    /**
     * Gitee OAuth2 authorization endpoint.
     * Verified correct as of 2024 - official Gitee OAuth endpoints.
     */
    protected const AUTHORIZE_URL = 'https://gitee.com/oauth/authorize';

    /**
     * Gitee token endpoint.
     * Verified correct as of 2024 - official Gitee OAuth endpoints.
     */
    protected const TOKEN_URL = 'https://gitee.com/oauth/token';

    /**
     * Gitee user info endpoint.
     * Verified correct as of 2024 - official Gitee API v5.
     */
    protected const USER_INFO_URL = 'https://gitee.com/api/v5/user';

    /**
     * Get Gitee authorization URL.
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

        $this->logActivity('Generated Gitee authorization URL', [
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
            'Accept' => 'application/json',
        ];

        $this->logActivity('Requesting Gitee access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        $this->handleOAuthError($response);

        $tokenData = $this->validateTokenResponse($response);

        $this->logActivity('Successfully obtained Gitee access token', [
            'token_type' => $tokenData['token_type'],
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
        $params = [
            'access_token' => $accessToken,
        ];

        $url = self::USER_INFO_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Requesting Gitee user info');

        $userData = $this->httpGet($url);

        // Check for API errors
        if (isset($userData['message'])) {
            throw new OAuthException("Gitee API error: {$userData['message']}");
        }

        $parsedUserData = $this->parseUserData($userData);

        $this->logActivity('Successfully retrieved Gitee user info', [
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
            'Accept' => 'application/json',
        ];

        $this->logActivity('Refreshing Gitee access token');

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        $this->handleOAuthError($response);

        $tokenData = $this->validateTokenResponse($response);

        $this->logActivity('Successfully refreshed Gitee access token', [
            'expires_in' => $tokenData['expires_in'],
        ]);

        return $tokenData;
    }

    /**
     * Extract user ID from Gitee user data.
     *
     * @param array $userData Raw user data from Gitee
     * @return string User ID
     */
    protected function getUserId(array $userData): string
    {
        return (string) ($userData['id'] ?? throw new OAuthException('Missing user ID in Gitee response'));
    }

    /**
     * Extract username from Gitee user data.
     *
     * @param array $userData Raw user data from Gitee
     * @return null|string Username
     */
    protected function getUsername(array $userData): ?string
    {
        return $userData['login'] ?? null;
    }

    /**
     * Extract display name from Gitee user data.
     *
     * @param array $userData Raw user data from Gitee
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['name'] ?? $userData['login'] ?? null;
    }

    /**
     * Extract email from Gitee user data.
     *
     * @param array $userData Raw user data from Gitee
     * @return null|string Email address
     */
    protected function getEmail(array $userData): ?string
    {
        return $userData['email'] ?? null;
    }

    /**
     * Extract avatar URL from Gitee user data.
     *
     * @param array $userData Raw user data from Gitee
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        return $userData['avatar_url'] ?? null;
    }
}
