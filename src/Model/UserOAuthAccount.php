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

namespace Plugin\MaimaiTech\OAuth2\Model;

use App\Model\Permission\User;
use Carbon\Carbon;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Model;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthProviderEnum;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthStatusEnum;

use function Hyperf\Support\now;

/**
 * @property int $id 主键
 * @property int $user_id 用户ID
 * @property string $provider OAuth服务商
 * @property string $provider_user_id 第三方平台用户ID
 * @property null|string $provider_username 第三方平台用户名
 * @property null|string $provider_email 第三方平台邮箱
 * @property null|string $provider_avatar 第三方平台头像URL
 * @property null|array $provider_data 第三方平台原始用户数据
 * @property null|string $access_token 访问令牌(加密存储)
 * @property null|string $refresh_token 刷新令牌(加密存储)
 * @property null|Carbon $token_expires_at 令牌过期时间
 * @property OAuthStatusEnum $status 状态
 * @property null|Carbon $last_login_at 最后登录时间
 * @property null|string $last_login_ip 最后登录IP
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $remark 备注
 * @property User $user 关联用户
 * @property null|OAuthProvider $oauthProvider OAuth提供商
 */
final class UserOAuthAccount extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'user_oauth_accounts';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'id',
        'user_id',
        'provider',
        'provider_user_id',
        'provider_username',
        'provider_email',
        'provider_avatar',
        'provider_data',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
        'last_login_at',
        'last_login_ip',
        'created_at',
        'updated_at',
        'remark',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'provider_data' => 'json',
        'token_expires_at' => 'datetime',
        'status' => OAuthStatusEnum::class,
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'access_token',
        'refresh_token',
        'provider_data',
    ];

    /**
     * Get the user that owns the OAuth account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the OAuth provider configuration.
     */
    public function oauthProvider(): BelongsTo
    {
        return $this->belongsTo(OAuthProvider::class, 'provider', 'name');
    }

    /**
     * Get the provider enum instance.
     */
    public function getProviderEnum(): ?OAuthProviderEnum
    {
        return OAuthProviderEnum::tryFrom($this->provider);
    }

    /**
     * Check if the account binding is active.
     */
    public function isActive(): bool
    {
        return $this->status->isNormal();
    }

    /**
     * Check if the account binding is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->status->isDisabled();
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (empty($this->token_expires_at)) {
            return false; // No expiration set, assume valid
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * Check if the access token is valid (not expired).
     */
    public function hasValidToken(): bool
    {
        return ! empty($this->access_token) && ! $this->isTokenExpired();
    }

    /**
     * Check if refresh token is available.
     */
    public function hasRefreshToken(): bool
    {
        return ! empty($this->refresh_token);
    }

    /**
     * Get provider display name.
     */
    public function getProviderDisplayName(): string
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getDisplayName() ?? $this->provider;
    }

    /**
     * Get provider brand color.
     */
    public function getProviderBrandColor(): string
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getBrandColor() ?? '#666666';
    }

    /**
     * Get provider icon.
     */
    public function getProviderIcon(): string
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getIcon() ?? 'icon-oauth';
    }

    /**
     * Update login information.
     */
    public function updateLoginInfo(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Update token information.
     */
    public function updateTokens(string $accessToken, ?string $refreshToken = null, ?Carbon $expiresAt = null): void
    {
        $this->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Update provider user data.
     */
    public function updateProviderData(array $userData): void
    {
        $this->update([
            'provider_username' => $userData['username'] ?? $userData['name'] ?? null,
            'provider_email' => $userData['email'] ?? null,
            'provider_avatar' => $userData['avatar'] ?? $userData['avatar_url'] ?? null,
            'provider_data' => $userData,
        ]);
    }

    /**
     * Encrypt access token before saving.
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        if (! empty($value)) {
            // TODO: Implement encryption using MineAdmin encryption service
            // For now, store as-is. In production, should encrypt the value.
            $this->attributes['access_token'] = $value;
        } else {
            $this->attributes['access_token'] = null;
        }
    }

    /**
     * Decrypt access token when accessed.
     */
    public function getAccessTokenAttribute(): ?string
    {
        $encryptedValue = $this->attributes['access_token'] ?? null;

        if (empty($encryptedValue)) {
            return null;
        }

        // TODO: Implement decryption using MineAdmin encryption service
        // For now, return as-is. In production, should decrypt the value.
        return $encryptedValue;
    }

    /**
     * Encrypt refresh token before saving.
     */
    public function setRefreshTokenAttribute(?string $value): void
    {
        if (! empty($value)) {
            // TODO: Implement encryption using MineAdmin encryption service
            // For now, store as-is. In production, should encrypt the value.
            $this->attributes['refresh_token'] = $value;
        } else {
            $this->attributes['refresh_token'] = null;
        }
    }

    /**
     * Decrypt refresh token when accessed.
     */
    public function getRefreshTokenAttribute(): ?string
    {
        $encryptedValue = $this->attributes['refresh_token'] ?? null;

        if (empty($encryptedValue)) {
            return null;
        }

        // TODO: Implement decryption using MineAdmin encryption service
        // For now, return as-is. In production, should decrypt the value.
        return $encryptedValue;
    }

    /**
     * Get formatted provider data for display.
     */
    public function getFormattedProviderData(): array
    {
        $data = $this->provider_data ?? [];

        return [
            'id' => $this->provider_user_id,
            'username' => $this->provider_username,
            'email' => $this->provider_email,
            'avatar' => $this->provider_avatar,
            'provider' => $this->getProviderDisplayName(),
            'provider_icon' => $this->getProviderIcon(),
            'provider_color' => $this->getProviderBrandColor(),
            'last_login' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'status' => $this->status->value,
            'has_valid_token' => $this->hasValidToken(),
            'raw_data' => $data,
        ];
    }
}
