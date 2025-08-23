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
 * WeChat OAuth2 Client for platform integration.
 *
 * @see https://developers.weixin.qq.com/doc/oplatform/Website_App/WeChat_Login/Wechat_Login.html
 */
class WeChatOAuthClient extends AbstractOAuthClient
{
    /**
     * WeChat OAuth2 authorization endpoint.
     * For WeChat Open Platform website applications (snsapi_login scope).
     */
    protected const AUTHORIZE_URL = 'https://open.weixin.qq.com/connect/qrconnect';

    /**
     * WeChat token endpoint.
     * Verified correct as of 2024 - official WeChat Open Platform API.
     */
    protected const TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    /**
     * WeChat user info endpoint.
     * Verified correct as of 2024 - official WeChat Open Platform API.
     */
    protected const USER_INFO_URL = 'https://api.weixin.qq.com/sns/userinfo';

    /**
     * WeChat token refresh endpoint.
     * Verified correct as of 2024 - official WeChat Open Platform API.
     */
    protected const REFRESH_TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';

    /**
     * Get WeChat authorization URL.
     *
     * @param string $state CSRF state parameter
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'appid' => $this->clientId, // WeChat uses 'appid' instead of 'client_id'
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => $this->normalizeScopesToString(),
            'state' => $state,
        ];

        $url = self::AUTHORIZE_URL . '?' . $this->buildQueryString($params) . '#wechat_redirect';

        $this->logActivity('Generated WeChat authorization URL', [
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
        $params = [
            'appid' => $this->clientId,
            'secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $url = self::TOKEN_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Requesting WeChat access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->httpGet($url);

        // Check for WeChat-specific errors
        if (isset($response['errcode'])) {
            throw new OAuthException(
                "WeChat OAuth error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        $tokenData = $this->validateWeChatTokenResponse($response);

        $this->logActivity('Successfully obtained WeChat access token', [
            'expires_in' => $tokenData['expires_in'],
            'has_refresh_token' => ! empty($tokenData['refresh_token']),
            'openid' => $response['openid'] ?? null,
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
        // For WeChat, we need the openid which should be stored with the token
        // This is typically handled by the calling service
        $openid = $this->getConfig('openid');
        if (empty($openid)) {
            throw new OAuthException('Missing openid for WeChat user info request');
        }

        $params = [
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => $this->getConfig('lang', 'zh_CN'), // Default to Chinese
        ];

        $url = self::USER_INFO_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Requesting WeChat user info', [
            'openid' => $openid,
        ]);

        $userData = $this->httpGet($url);

        // Check for WeChat-specific errors
        if (isset($userData['errcode'])) {
            throw new OAuthException(
                "WeChat user info error: {$userData['errmsg']} (code: {$userData['errcode']})"
            );
        }

        $parsedUserData = $this->parseUserData($userData);

        $this->logActivity('Successfully retrieved WeChat user info', [
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
        $params = [
            'appid' => $this->clientId,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $url = self::REFRESH_TOKEN_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Refreshing WeChat access token');

        $response = $this->httpGet($url);

        // Check for WeChat-specific errors
        if (isset($response['errcode'])) {
            throw new OAuthException(
                "WeChat token refresh error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        $tokenData = $this->validateWeChatTokenResponse($response);

        $this->logActivity('Successfully refreshed WeChat access token', [
            'expires_in' => $tokenData['expires_in'],
        ]);

        return $tokenData;
    }

    /**
     * Get additional WeChat-specific token data.
     *
     * @param array $tokenData Token data from WeChat
     * @return array Additional data including openid and unionid
     */
    public function getWeChatTokenData(array $tokenData): array
    {
        return [
            'openid' => $tokenData['openid'] ?? null,
            'unionid' => $tokenData['unionid'] ?? null,
        ];
    }

    /**
     * Extract user ID from WeChat user data.
     *
     * @param array $userData Raw user data from WeChat
     * @return string User ID (unionid preferred over openid)
     */
    protected function getUserId(array $userData): string
    {
        // Prefer unionid if available (consistent across apps in same WeChat Open Platform account)
        return $userData['unionid'] ?? $userData['openid'] ?? throw new OAuthException('Missing user ID in WeChat response');
    }

    /**
     * Extract username from WeChat user data.
     *
     * @param array $userData Raw user data from WeChat
     * @return null|string Username (WeChat doesn't have a username concept, use nickname)
     */
    protected function getUsername(array $userData): ?string
    {
        return $userData['nickname'] ?? null;
    }

    /**
     * Extract display name from WeChat user data.
     *
     * @param array $userData Raw user data from WeChat
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['nickname'] ?? null;
    }

    /**
     * Extract email from WeChat user data.
     * Note: WeChat doesn't provide email information through OAuth.
     *
     * @param array $userData Raw user data from WeChat
     * @return null|string Email address (always null for WeChat)
     */
    protected function getEmail(array $userData): ?string
    {
        // WeChat doesn't provide email through OAuth
        return null;
    }

    /**
     * Extract avatar URL from WeChat user data.
     *
     * @param array $userData Raw user data from WeChat
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        // Use high-resolution avatar if available, fall back to standard
        return $userData['headimgurl'] ?? null;
    }

    /**
     * Validate WeChat token response.
     *
     * @param array $response Token response from WeChat
     * @return array Validated token data
     * @throws OAuthException
     */
    protected function validateWeChatTokenResponse(array $response): array
    {
        if (isset($response['errcode'])) {
            throw new OAuthException(
                "WeChat token error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        if (! isset($response['access_token'])) {
            throw new OAuthException('Missing access_token in WeChat response');
        }

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'token_type' => 'Bearer',
            'expires_in' => isset($response['expires_in']) ? (int) $response['expires_in'] : null,
            'scope' => $response['scope'] ?? null,
            'openid' => $response['openid'] ?? null, // WeChat-specific
            'unionid' => $response['unionid'] ?? null, // WeChat-specific
        ];
    }
}
