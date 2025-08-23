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
 * DingTalk OAuth2 Client for platform integration.
 *
 * @see https://open.dingtalk.com/document/isvapp-server/authorization-process
 */
class DingTalkOAuthClient extends AbstractOAuthClient
{
    /**
     * DingTalk OAuth2 authorization endpoint.
     * Verified correct as of 2024 - using official DingTalk login domain.
     */
    protected const AUTHORIZE_URL = 'https://login.dingtalk.com/oauth2/auth';

    /**
     * DingTalk token endpoint.
     * Verified correct as of 2024 - using v1.0 API for user access tokens.
     */
    protected const TOKEN_URL = 'https://api.dingtalk.com/v1.0/oauth2/userAccessToken';

    /**
     * DingTalk user info endpoint.
     * Based on the official Java SDK documentation, using contact API with 'me' endpoint.
     */
    protected const USER_INFO_URL = 'https://api.dingtalk.com/v1.0/contact/users/me';

    /**
     * Get DingTalk authorization URL.
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
            'prompt' => 'consent',
        ];

        // Add corpId if it's configured (for enterprise apps)
        $corpId = $this->getConfig('corpId');
        if (! empty($corpId)) {
            $params['corpId'] = $corpId;
        }

        $url = self::AUTHORIZE_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Generated DingTalk authorization URL', [
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
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'code' => $code,
            'grantType' => 'authorization_code',
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $this->logActivity('Requesting DingTalk access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        // Handle DingTalk-specific error format
        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            throw new OAuthException(
                "DingTalk OAuth error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        $tokenData = $this->validateTokenResponse($response);

        $this->logActivity('Successfully obtained DingTalk access token', [
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
            'x-acs-dingtalk-access-token' => $accessToken,
        ];

        $this->logActivity('Requesting DingTalk user info', [
            'url' => self::USER_INFO_URL,
            'headers' => array_keys($headers), // Log header keys only for security
        ]);

        try {
            $response = $this->httpGet(self::USER_INFO_URL, $headers);
        } catch (\Throwable $e) {
            // Enhanced error logging for DingTalk permission issues
            if (str_contains($e->getMessage(), 'AccessTokenPermissionDenied')) {
                $this->logActivity('DingTalk permission error - missing contact read permissions', [
                    'error' => $e->getMessage(),
                    'solution' => 'Please add Contact.User.Read permission in DingTalk developer console',
                ]);
                throw new OAuthException(
                    'DingTalk permission denied: The application lacks permission to read user contact information. '
                    . "Please add 'Contact.User.Read' permission in DingTalk developer console and regenerate the access token."
                );
            }
            throw $e;
        }

        // Based on Java SDK documentation, DingTalk contact API returns user data directly
        // The response should contain user fields like nick, unionid, userid, etc.
        if (empty($response) || (! isset($response['nick']) && ! isset($response['name']) && ! isset($response['unionid']))) {
            // Log the response for debugging
            $this->logActivity('DingTalk user info API returned unexpected format', [
                'response' => $response,
            ]);
            throw new OAuthException('Invalid DingTalk user info response format or empty response');
        }

        $userData = $this->parseUserData($response);

        $this->logActivity('Successfully retrieved DingTalk user info', [
            'user_id' => $userData['id'],
            'username' => $userData['username'],
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => null, // Will be set by caller if available
            'token_type' => 'Bearer',
            'expires_in' => null, // Will be set by caller if available
            'user_data' => $userData,
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
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'refreshToken' => $refreshToken,
            'grantType' => 'refresh_token',
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $this->logActivity('Refreshing DingTalk access token');

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        // Handle DingTalk-specific error format
        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            throw new OAuthException(
                "DingTalk token refresh error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        $tokenData = $this->validateTokenResponse($response);

        $this->logActivity('Successfully refreshed DingTalk access token', [
            'expires_in' => $tokenData['expires_in'],
        ]);

        return $tokenData;
    }

    /**
     * Validate DingTalk-specific configuration.
     * Note: corpId is optional for third-party applications.
     *
     * @throws OAuthException
     */
    protected function validateConfiguration(): void
    {
        parent::validateConfiguration();

        // corpId is optional for third-party applications
        // Only log a warning if it's missing
        if (empty($this->getConfig('corpId'))) {
            $this->logActivity('Warning: corpId not configured for DingTalk provider (optional for third-party apps)', [
                'provider' => $this->provider->name,
            ]);
        }
    }

    /**
     * Extract user ID from DingTalk user data.
     *
     * @param array $userData Raw user data from DingTalk
     * @return string User ID (unionId preferred, then userid, then openId)
     * @throws OAuthException
     */
    protected function getUserId(array $userData): string
    {
        // Based on DingTalk documentation, prioritize unionId as it's the most stable identifier
        return $userData['unionid'] ?? $userData['unionId'] ?? $userData['userid'] ?? $userData['openId'] ?? throw new OAuthException('Missing user ID in DingTalk response');
    }

    /**
     * Extract username from DingTalk user data.
     *
     * @param array $userData Raw user data from DingTalk
     * @return null|string Username
     */
    protected function getUsername(array $userData): ?string
    {
        // Based on DingTalk API documentation field names
        return $userData['nick'] ?? $userData['name'] ?? $userData['real_name'] ?? null;
    }

    /**
     * Extract display name from DingTalk user data.
     *
     * @param array $userData Raw user data from DingTalk
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['nick'] ?? $userData['name'] ?? $userData['real_name'] ?? null;
    }

    /**
     * Extract email from DingTalk user data.
     *
     * @param array $userData Raw user data from DingTalk
     * @return null|string Email address
     */
    protected function getEmail(array $userData): ?string
    {
        return $userData['email'] ?? null;
    }

    /**
     * Extract avatar URL from DingTalk user data.
     *
     * @param array $userData Raw user data from DingTalk
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        // DingTalk typically uses 'avatar' field according to the documentation
        return $userData['avatar'] ?? $userData['avatarUrl'] ?? null;
    }

    /**
     * Validate DingTalk token response.
     *
     * @param array $response Token response from DingTalk
     * @return array Validated token data
     * @throws OAuthException
     */
    protected function validateTokenResponse(array $response): array
    {
        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            throw new OAuthException(
                "DingTalk token error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        if (! isset($response['accessToken'])) {
            throw new OAuthException('Missing accessToken in DingTalk response');
        }

        return [
            'access_token' => $response['accessToken'],
            'refresh_token' => $response['refreshToken'] ?? null,
            'token_type' => 'Bearer',
            'expires_in' => isset($response['expireIn']) ? (int) $response['expireIn'] : null,
            'scope' => $response['scope'] ?? null,
        ];
    }
}
