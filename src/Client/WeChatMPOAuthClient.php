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
 * WeChat Mini Program (公众号) OAuth2 Client for in-WeChat web application integration.
 *
 * 专门用于微信公众号内网页授权，支持在微信内置浏览器中的OAuth流程
 *
 * @see https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html
 */
class WeChatMPOAuthClient extends AbstractOAuthClient
{
    /**
     * WeChat MP OAuth2 authorization endpoint.
     * For WeChat Official Account web applications (snsapi_base/snsapi_userinfo scopes).
     */
    protected const AUTHORIZE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * WeChat token endpoint (same as Open Platform).
     * Verified correct as of 2024 - official WeChat API.
     */
    protected const TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    /**
     * WeChat user info endpoint (same as Open Platform).
     * Verified correct as of 2024 - official WeChat API.
     */
    protected const USER_INFO_URL = 'https://api.weixin.qq.com/sns/userinfo';

    /**
     * WeChat token refresh endpoint (same as Open Platform).
     * Verified correct as of 2024 - official WeChat API.
     */
    protected const REFRESH_TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';

    /**
     * Get WeChat MP authorization URL.
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

        $this->logActivity('Generated WeChat MP authorization URL', [
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

        $this->logActivity('Requesting WeChat MP access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->httpGet($url);

        // Check for WeChat-specific errors
        if (isset($response['errcode'])) {
            throw new OAuthException(
                "WeChat MP OAuth error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        $tokenData = $this->validateWeChatTokenResponse($response);

        $this->logActivity('Successfully obtained WeChat MP access token', [
            'expires_in' => $tokenData['expires_in'],
            'has_refresh_token' => ! empty($tokenData['refresh_token']),
            'openid' => $response['openid'] ?? null,
            'scope' => $response['scope'] ?? null,
        ]);

        return $tokenData;
    }

    /**
     * Get user information using access token.
     *
     * 注意：只有当scope为snsapi_userinfo时才能获取用户详细信息
     * 当scope为snsapi_base时只能获取openid
     *
     * @param string $accessToken Access token
     * @return array Complete user info with standardized format
     * @throws OAuthException
     */
    public function getUserInfo(string $accessToken): array
    {
        // For WeChat MP, we need the openid which should be stored with the token
        $openid = $this->getConfig('openid');
        if (empty($openid)) {
            throw new OAuthException('Missing openid for WeChat MP user info request');
        }

        // Check if we have snsapi_userinfo scope
        $scope = $this->getConfig('scope', '');
        if (! str_contains($scope, 'snsapi_userinfo')) {
            // If only snsapi_base scope, return minimal user data
            $this->logActivity('WeChat MP snsapi_base scope - returning minimal user data', [
                'openid' => $openid,
            ]);

            return [
                'access_token' => $accessToken,
                'refresh_token' => null,
                'token_type' => 'Bearer',
                'expires_in' => null,
                'user_data' => [
                    'id' => $openid,
                    'username' => null,
                    'name' => null,
                    'nickname' => null,
                    'email' => null,
                    'avatar' => null,
                    'raw' => ['openid' => $openid],
                ],
            ];
        }

        $params = [
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => $this->getConfig('lang', 'zh_CN'), // Default to Chinese
        ];

        $url = self::USER_INFO_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Requesting WeChat MP user info', [
            'openid' => $openid,
        ]);

        $userData = $this->httpGet($url);

        // Check for WeChat-specific errors
        if (isset($userData['errcode'])) {
            throw new OAuthException(
                "WeChat MP user info error: {$userData['errmsg']} (code: {$userData['errcode']})"
            );
        }

        $parsedUserData = $this->parseUserData($userData);

        $this->logActivity('Successfully retrieved WeChat MP user info', [
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

        $this->logActivity('Refreshing WeChat MP access token');

        $response = $this->httpGet($url);

        // Check for WeChat-specific errors
        if (isset($response['errcode'])) {
            throw new OAuthException(
                "WeChat MP token refresh error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        $tokenData = $this->validateWeChatTokenResponse($response);

        $this->logActivity('Successfully refreshed WeChat MP access token', [
            'expires_in' => $tokenData['expires_in'],
        ]);

        return $tokenData;
    }

    /**
     * Get additional WeChat MP-specific token data.
     *
     * @param array $tokenData Token data from WeChat MP
     * @return array Additional data including openid and unionid
     */
    public function getWeChatMPTokenData(array $tokenData): array
    {
        return [
            'openid' => $tokenData['openid'] ?? null,
            'unionid' => $tokenData['unionid'] ?? null,
            'scope' => $tokenData['scope'] ?? null,
        ];
    }

    /**
     * Check if current scope supports getting user info.
     *
     * @return bool True if snsapi_userinfo scope is available
     */
    public function supportsUserInfo(): bool
    {
        $scopes = $this->normalizeScopesToArray();
        return \in_array('snsapi_userinfo', $scopes, true);
    }

    /**
     * Get scope description for WeChat MP.
     *
     * @param string $scope Scope name
     * @return string Scope description
     */
    public function getScopeDescription(string $scope): string
    {
        $descriptions = [
            'snsapi_base' => '静默授权，只能获取openid',
            'snsapi_userinfo' => '需要用户确认，可获取用户详细信息',
        ];

        return $descriptions[$scope] ?? $scope;
    }

    /**
     * Extract user ID from WeChat MP user data.
     *
     * @param array $userData Raw user data from WeChat MP
     * @return string User ID (unionid preferred over openid)
     */
    protected function getUserId(array $userData): string
    {
        // Prefer unionid if available (consistent across apps in same WeChat ecosystem)
        return $userData['unionid'] ?? $userData['openid'] ?? throw new OAuthException('Missing user ID in WeChat MP response');
    }

    /**
     * Extract username from WeChat MP user data.
     *
     * @param array $userData Raw user data from WeChat MP
     * @return null|string Username (WeChat doesn't have a username concept, use nickname)
     */
    protected function getUsername(array $userData): ?string
    {
        return $userData['nickname'] ?? null;
    }

    /**
     * Extract display name from WeChat MP user data.
     *
     * @param array $userData Raw user data from WeChat MP
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['nickname'] ?? null;
    }

    /**
     * Extract email from WeChat MP user data.
     * Note: WeChat MP doesn't provide email information through OAuth.
     *
     * @param array $userData Raw user data from WeChat MP
     * @return null|string Email address (always null for WeChat MP)
     */
    protected function getEmail(array $userData): ?string
    {
        // WeChat MP doesn't provide email through OAuth
        return null;
    }

    /**
     * Extract avatar URL from WeChat MP user data.
     *
     * @param array $userData Raw user data from WeChat MP
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        // Use high-resolution avatar if available, fall back to standard
        return $userData['headimgurl'] ?? null;
    }

    /**
     * Validate WeChat MP token response.
     *
     * @param array $response Token response from WeChat MP
     * @return array Validated token data
     * @throws OAuthException
     */
    protected function validateWeChatTokenResponse(array $response): array
    {
        if (isset($response['errcode'])) {
            throw new OAuthException(
                "WeChat MP token error: {$response['errmsg']} (code: {$response['errcode']})"
            );
        }

        if (! isset($response['access_token'])) {
            throw new OAuthException('Missing access_token in WeChat MP response');
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

    /**
     * Normalize scopes to string for WeChat MP.
     * WeChat MP only supports single scope at a time.
     *
     * @return string Scope string
     */
    protected function normalizeScopesToString(): string
    {
        $scopes = $this->normalizeScopesToArray();

        // WeChat MP only supports one scope at a time
        // Prefer snsapi_userinfo over snsapi_base
        if (\in_array('snsapi_userinfo', $scopes, true)) {
            return 'snsapi_userinfo';
        }

        if (\in_array('snsapi_base', $scopes, true)) {
            return 'snsapi_base';
        }

        // Default to snsapi_base if no valid scope is found
        return 'snsapi_base';
    }
}
