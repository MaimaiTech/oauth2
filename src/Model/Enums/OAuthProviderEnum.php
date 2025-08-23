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

namespace Plugin\MaimaiTech\OAuth2\Model\Enums;

use Hyperf\Constants\Annotation\Constants;
use Hyperf\Constants\Annotation\Message;
use Hyperf\Constants\EnumConstantsTrait;

#[Constants]
enum OAuthProviderEnum: string
{
    use EnumConstantsTrait;

    #[Message('oauth2.provider.dingtalk')]
    case DINGTALK = 'dingtalk';

    #[Message('oauth2.provider.github')]
    case GITHUB = 'github';

    #[Message('oauth2.provider.gitee')]
    case GITEE = 'gitee';

    #[Message('oauth2.provider.feishu')]
    case FEISHU = 'feishu';

    #[Message('oauth2.provider.wechat')]
    case WECHAT = 'wechat';

    #[Message('oauth2.provider.qq')]
    case QQ = 'qq';

    /**
     * Get display name for the provider.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::DINGTALK => '钉钉',
            self::GITHUB => 'GitHub',
            self::GITEE => '码云',
            self::FEISHU => '飞书',
            self::WECHAT => '微信',
            self::QQ => 'QQ',
        };
    }

    /**
     * Get icon class for the provider.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::DINGTALK => 'icon-dingtalk',
            self::GITHUB => 'icon-github',
            self::GITEE => 'icon-gitee',
            self::FEISHU => 'icon-feishu',
            self::WECHAT => 'icon-wechat',
            self::QQ => 'icon-qq',
        };
    }

    /**
     * Get brand color for the provider.
     */
    public function getBrandColor(): string
    {
        return match ($this) {
            self::DINGTALK => '#007aff',
            self::GITHUB => '#24292e',
            self::GITEE => '#c71d23',
            self::FEISHU => '#00d4aa',
            self::WECHAT => '#07c160',
            self::QQ => '#12b7f5',
        };
    }

    /**
     * Get default OAuth scopes for the provider.
     */
    public function getDefaultScopes(): array
    {
        return match ($this) {
            self::DINGTALK => ['openid'],
            self::GITHUB => ['user:email'],
            self::GITEE => ['user_info'],
            self::FEISHU => ['contact:user.id:read'],
            self::WECHAT => ['snsapi_userinfo'],
            self::QQ => ['get_user_info'],
        };
    }

    /**
     * Check if provider supports refresh tokens.
     */
    public function supportsRefreshToken(): bool
    {
        return match ($this) {
            self::DINGTALK => true,
            self::GITHUB => false, // GitHub tokens don't expire
            self::GITEE => true,
            self::FEISHU => true,
            self::WECHAT => true,
            self::QQ => true,
        };
    }

    /**
     * Get all supported providers.
     */
    public static function getAllProviders(): array
    {
        return [
            self::DINGTALK,
            self::GITHUB,
            self::GITEE,
            self::FEISHU,
            self::WECHAT,
            self::QQ,
        ];
    }
}
