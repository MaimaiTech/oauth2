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

use Plugin\MaimaiTech\OAuth2\Client\AbstractOAuthClient;
use Plugin\MaimaiTech\OAuth2\Client\DingTalkOAuthClient;
use Plugin\MaimaiTech\OAuth2\Client\FeishuOAuthClient;
use Plugin\MaimaiTech\OAuth2\Client\GiteeOAuthClient;
use Plugin\MaimaiTech\OAuth2\Client\GitHubOAuthClient;
use Plugin\MaimaiTech\OAuth2\Client\QQOAuthClient;
use Plugin\MaimaiTech\OAuth2\Client\WeChatOAuthClient;
use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthProviderEnum;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;

/**
 * Factory class for creating OAuth2 client instances.
 */
final class OAuthClientFactory
{
    /**
     * Create OAuth client instance for the given provider.
     *
     * @throws OAuthException
     */
    public static function create(OAuthProvider $provider): AbstractOAuthClient
    {
        if (! $provider->isEnabled()) {
            throw OAuthException::configurationError(
                "OAuth provider '{$provider->name}' is disabled",
                $provider->name
            );
        }

        $clientClass = self::getClientMaps()[$provider->name] ?? null;

        if (! $clientClass) {
            throw OAuthException::configurationError(
                "No OAuth client found for provider: {$provider->name}",
                $provider->name
            );
        }

        if (! class_exists($clientClass)) {
            throw OAuthException::configurationError(
                "OAuth client class does not exist: {$clientClass}",
                $provider->name
            );
        }

        try {
            return new $clientClass($provider);
        } catch (\Throwable $e) {
            throw OAuthException::configurationError(
                "Failed to create OAuth client for {$provider->name}: {$e->getMessage()}",
                $provider->name
            );
        }
    }

    /**
     * Create OAuth client by provider name.
     *
     * @throws OAuthException
     */
    public static function createByName(string $providerName, ProviderService $providerService): AbstractOAuthClient
    {
        $provider = $providerService->getProvider($providerName);

        if (! $provider) {
            throw OAuthException::configurationError(
                "OAuth provider not found: {$providerName}",
                $providerName
            );
        }

        return self::create($provider);
    }

    /**
     * Get available OAuth provider names.
     */
    public static function getAvailableProviders(): array
    {
        return array_keys(self::getClientMaps());
    }

    /**
     * Check if a provider is supported.
     */
    public static function isProviderSupported(string $providerName): bool
    {
        return isset(self::getClientMaps()[$providerName]);
    }

    /**
     * Get client class name for a provider.
     */
    public static function getClientClass(string $providerName): ?string
    {
        return self::getClientMaps()[$providerName] ?? null;
    }

    /**
     * Validate OAuth provider configuration without creating client.
     *
     * @throws OAuthException
     */
    public static function validateProvider(OAuthProvider $provider): bool
    {
        if (! self::isProviderSupported($provider->name)) {
            throw OAuthException::configurationError(
                "Provider '{$provider->name}' is not supported",
                $provider->name
            );
        }

        if (empty($provider->client_id)) {
            throw OAuthException::configurationError(
                "Missing client_id for provider: {$provider->name}",
                $provider->name
            );
        }

        if (empty($provider->client_secret)) {
            throw OAuthException::configurationError(
                "Missing client_secret for provider: {$provider->name}",
                $provider->name
            );
        }

        if (empty($provider->redirect_uri)) {
            throw OAuthException::configurationError(
                "Missing redirect_uri for provider: {$provider->name}",
                $provider->name
            );
        }

        // Validate redirect URI format
        if (! filter_var($provider->redirect_uri, \FILTER_VALIDATE_URL)) {
            throw OAuthException::configurationError(
                "Invalid redirect_uri format for provider: {$provider->name}",
                $provider->name
            );
        }

        return true;
    }

    /**
     * Get provider requirements and documentation.
     */
    public static function getProviderInfo(string $providerName): array
    {
        if (! self::isProviderSupported($providerName)) {
            return [];
        }

        $info = [
            'name' => $providerName,
            'supported' => true,
            'client_class' => self::getClientMaps()[$providerName],
            'required_fields' => ['client_id', 'client_secret', 'redirect_uri'],
            'optional_fields' => ['scopes', 'extra_config'],
        ];

        // Add provider-specific information
        switch ($providerName) {
            case OAuthProviderEnum::DINGTALK->value:
                $info['documentation'] = 'https://dingtalk.apifox.cn/llms.txt';
                $info['default_scopes'] = ['openid'];
                $info['supports_refresh'] = true;
                break;
            case OAuthProviderEnum::GITHUB->value:
                $info['documentation'] = 'https://docs.github.com/apps/oauth-apps';
                $info['default_scopes'] = ['user:email'];
                $info['supports_refresh'] = false;
                break;
            case OAuthProviderEnum::GITEE->value:
                $info['documentation'] = 'https://gitee.com/api/v5/oauth_doc';
                $info['default_scopes'] = ['user_info'];
                $info['supports_refresh'] = true;
                break;
            case OAuthProviderEnum::FEISHU->value:
                $info['documentation'] = 'https://open.feishu.cn/document/sso/web-application-sso';
                $info['default_scopes'] = ['contact:user.id:read'];
                $info['supports_refresh'] = true;
                break;
            case OAuthProviderEnum::WECHAT->value:
                $info['documentation'] = 'https://developers.weixin.qq.com/doc/oplatform/Website_App/WeChat_Login/Wechat_Login.html';
                $info['default_scopes'] = ['snsapi_login'];
                $info['supports_refresh'] = true;
                break;
            case OAuthProviderEnum::QQ->value:
                $info['documentation'] = 'https://wiki.connect.qq.com/';
                $info['default_scopes'] = ['get_user_info'];
                $info['supports_refresh'] = true;
                break;
        }

        return $info;
    }

    /**
     * Get all provider information.
     */
    public static function getAllProvidersInfo(): array
    {
        $providers = [];
        foreach (self::getAvailableProviders() as $providerName) {
            $providers[$providerName] = self::getProviderInfo($providerName);
        }
        return $providers;
    }

    /**
     * Test OAuth client creation without making any requests.
     */
    public static function testClientCreation(OAuthProvider $provider): array
    {
        try {
            self::validateProvider($provider);
            $client = self::create($provider);

            return [
                'success' => true,
                'provider' => $provider->name,
                'client_class' => $client::class,
                'debug_info' => $client->getDebugInfo(),
                'auth_url' => $client->getAuthorizationUrl(uniqid('test')),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'provider' => $provider->name,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    private static function getClientMaps(): array
    {
        return [
            OAuthProviderEnum::DINGTALK->value => DingTalkOAuthClient::class,
            OAuthProviderEnum::GITHUB->value => GitHubOAuthClient::class,
            OAuthProviderEnum::GITEE->value => GiteeOAuthClient::class,
            OAuthProviderEnum::FEISHU->value => FeishuOAuthClient::class,
            OAuthProviderEnum::WECHAT->value => WeChatOAuthClient::class,
            OAuthProviderEnum::QQ->value => QQOAuthClient::class,
        ];
    }
}
