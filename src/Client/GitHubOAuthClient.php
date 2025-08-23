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
 * GitHub OAuth2 Client for platform integration.
 *
 * @see https://docs.github.com/en/developers/apps/building-oauth-apps/authorizing-oauth-apps
 */
class GitHubOAuthClient extends AbstractOAuthClient
{
    /**
     * GitHub OAuth2 authorization endpoint.
     * Verified correct as of 2024 - official GitHub OAuth endpoints.
     */
    protected const AUTHORIZE_URL = 'https://github.com/login/oauth/authorize';

    /**
     * GitHub token endpoint.
     * Verified correct as of 2024 - official GitHub OAuth endpoints.
     */
    protected const TOKEN_URL = 'https://github.com/login/oauth/access_token';

    /**
     * GitHub user info endpoint.
     * Verified correct as of 2024 - official GitHub REST API v3.
     */
    protected const USER_INFO_URL = 'https://api.github.com/user';

    /**
     * GitHub user emails endpoint.
     * Verified correct as of 2024 - official GitHub REST API v3.
     */
    protected const USER_EMAILS_URL = 'https://api.github.com/user/emails';

    /**
     * Get GitHub authorization URL.
     *
     * @param string $state CSRF state parameter
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->normalizeScopesToString(),
            'state' => $state,
            'response_type' => 'code',
        ];

        // Add allow_signup parameter if configured
        if ($this->getConfig('allow_signup') !== null) {
            $params['allow_signup'] = $this->getConfig('allow_signup') ? 'true' : 'false';
        }

        $url = self::AUTHORIZE_URL . '?' . $this->buildQueryString($params);

        $this->logActivity('Generated GitHub authorization URL', [
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
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
        ];

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'MineAdmin OAuth2 Client/1.0',
        ];

        $this->logActivity('Requesting GitHub access token', [
            'code_length' => mb_strlen($code),
        ]);

        $response = $this->httpPost(self::TOKEN_URL, $data, $headers);

        $this->handleOAuthError($response);

        $tokenData = $this->validateTokenResponse($response);

        $this->logActivity('Successfully obtained GitHub access token', [
            'token_type' => $tokenData['token_type'],
            'scope' => $tokenData['scope'],
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
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'MineAdmin OAuth2 Client/1.0',
        ];

        $this->logActivity('Requesting GitHub user info');

        $userData = $this->httpGet(self::USER_INFO_URL, $headers);

        // Get user emails if the email is not public
        $email = $userData['email'] ?? null;
        if (empty($email) && \in_array('user:email', $this->scopes, true)) {
            try {
                $this->logActivity('Requesting GitHub user emails (email not public)');
                $emails = $this->httpGet(self::USER_EMAILS_URL, $headers);

                // Find the primary email
                foreach ($emails as $emailData) {
                    if ($emailData['primary'] === true) {
                        $email = $emailData['email'];
                        break;
                    }
                }

                // If no primary email found, use the first verified one
                if (empty($email)) {
                    foreach ($emails as $emailData) {
                        if ($emailData['verified'] === true) {
                            $email = $emailData['email'];
                            break;
                        }
                    }
                }
            } catch (OAuthException $e) {
                $this->logActivity('Failed to retrieve GitHub user emails', [
                    'error' => $e->getMessage(),
                ]);
                // Continue without email if we can't retrieve it
            }
        }

        // Add the retrieved email to userData for parsing
        if ($email) {
            $userData['email'] = $email;
        }

        $parsedUserData = $this->parseUserData($userData);

        $this->logActivity('Successfully retrieved GitHub user info', [
            'user_id' => $parsedUserData['id'],
            'username' => $parsedUserData['username'],
            'has_email' => ! empty($parsedUserData['email']),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => null, // GitHub doesn't provide refresh tokens
            'token_type' => 'Bearer',
            'expires_in' => null, // GitHub tokens don't expire
            'user_data' => $parsedUserData,
        ];
    }

    /**
     * Refresh access token using refresh token.
     * Note: GitHub doesn't support refresh tokens.
     *
     * @param string $refreshToken Refresh token
     * @return array New token response
     * @throws OAuthException
     */
    public function refreshToken(string $refreshToken): array
    {
        throw new OAuthException('GitHub does not support refresh tokens');
    }

    /**
     * Check if provider supports refresh tokens.
     * GitHub doesn't support refresh tokens.
     */
    public function supportsRefreshToken(): bool
    {
        return false;
    }

    /**
     * Extract user ID from GitHub user data.
     *
     * @param array $userData Raw user data from GitHub
     * @return string User ID
     */
    protected function getUserId(array $userData): string
    {
        return (string) ($userData['id'] ?? throw new OAuthException('Missing user ID in GitHub response'));
    }

    /**
     * Extract username from GitHub user data.
     *
     * @param array $userData Raw user data from GitHub
     * @return null|string Username
     */
    protected function getUsername(array $userData): ?string
    {
        return $userData['login'] ?? null;
    }

    /**
     * Extract display name from GitHub user data.
     *
     * @param array $userData Raw user data from GitHub
     * @return null|string Display name
     */
    protected function getDisplayName(array $userData): ?string
    {
        return $userData['name'] ?? $userData['login'] ?? null;
    }

    /**
     * Extract email from GitHub user data.
     *
     * @param array $userData Raw user data from GitHub
     * @return null|string Email address
     */
    protected function getEmail(array $userData): ?string
    {
        return $userData['email'] ?? null;
    }

    /**
     * Extract avatar URL from GitHub user data.
     *
     * @param array $userData Raw user data from GitHub
     * @return null|string Avatar URL
     */
    protected function getAvatar(array $userData): ?string
    {
        return $userData['avatar_url'] ?? null;
    }
}
