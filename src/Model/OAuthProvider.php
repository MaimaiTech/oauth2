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

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthProviderEnum;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthStatusEnum;

/**
 * @property int $id 主键
 * @property string $name 服务商名称
 * @property string $display_name 显示名称
 * @property string $client_id 应用ID/客户端ID
 * @property string $client_secret 应用密钥/客户端密钥(加密存储)
 * @property string $redirect_uri 回调地址
 * @property null|array $scopes OAuth授权范围
 * @property null|array $extra_config 平台特定配置参数
 * @property int $enabled 启用状态
 * @property OAuthStatusEnum $status 状态
 * @property int $sort 排序
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property null|Carbon $deleted_at 删除时间
 * @property string $remark 备注
 * @property Collection|UserOAuthAccount[] $userAccounts 用户绑定账户
 */
final class OAuthProvider extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'oauth_providers';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'id',
        'name',
        'display_name',
        'client_id',
        'client_secret',
        'redirect_uri',
        'scopes',
        'extra_config',
        'enabled',
        'status',
        'sort',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'remark',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'id' => 'integer',
        'scopes' => 'json',
        'extra_config' => 'json',
        'enabled' => 'boolean',
        'status' => OAuthStatusEnum::class,
        'sort' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'client_secret',
    ];

    /**
     * Get the user OAuth accounts for the provider.
     */
    public function userAccounts(): HasMany
    {
        return $this->hasMany(UserOAuthAccount::class, 'provider', 'name');
    }

    /**
     * Get the provider enum instance.
     */
    public function getProviderEnum(): ?OAuthProviderEnum
    {
        return OAuthProviderEnum::tryFrom($this->name);
    }

    /**
     * Check if the provider is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->status->isNormal();
    }

    /**
     * Check if the provider is disabled.
     */
    public function isDisabled(): bool
    {
        return ! $this->enabled || $this->status->isDisabled();
    }

    /**
     * Get display name with fallback.
     */
    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->attributes['display_name'])) {
            return $this->attributes['display_name'];
        }

        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getDisplayName() ?? $this->name;
    }

    /**
     * Get default scopes for the provider.
     */
    public function getDefaultScopes(): array
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getDefaultScopes() ?? [];
    }

    /**
     * Get effective scopes (custom or default).
     */
    public function getEffectiveScopes(): array
    {
        return $this->scopes ?? $this->getDefaultScopes();
    }

    /**
     * Check if provider supports refresh tokens.
     */
    public function supportsRefreshToken(): bool
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->supportsRefreshToken() ?? false;
    }

    /**
     * Get brand color for the provider.
     */
    public function getBrandColor(): string
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getBrandColor() ?? '#666666';
    }

    /**
     * Get icon class for the provider.
     */
    public function getIcon(): string
    {
        $providerEnum = $this->getProviderEnum();
        return $providerEnum?->getIcon() ?? 'icon-oauth';
    }

    /**
     * Encrypt the client secret before saving.
     */
    public function setClientSecretAttribute(string $value): void
    {
        if (! empty($value)) {
            // TODO: Implement encryption using MineAdmin encryption service
            // For now, store as-is. In production, should encrypt the value.
            $this->attributes['client_secret'] = $value;
        }
    }

    /**
     * Decrypt the client secret when accessed.
     */
    public function getClientSecretAttribute(): ?string
    {
        $encryptedValue = $this->attributes['client_secret'] ?? null;

        if (empty($encryptedValue)) {
            return null;
        }

        // TODO: Implement decryption using MineAdmin encryption service
        // For now, return as-is. In production, should decrypt the value.
        return $encryptedValue;
    }

    /**
     * Set default sort when creating.
     */
    public function creating(Creating $event): void
    {
        if (empty($this->sort)) {
            $maxSort = self::query()->max('sort') ?? 0;
            $this->sort = $maxSort + 10;
        }
    }

    /**
     * Get statistics for the provider.
     */
    public function getStats(): array
    {
        $totalBindings = $this->userAccounts()->count();
        $activeBindings = $this->userAccounts()->where('status', OAuthStatusEnum::NORMAL)->count();

        return [
            'total_bindings' => $totalBindings,
            'active_bindings' => $activeBindings,
            'inactive_bindings' => $totalBindings - $activeBindings,
        ];
    }
}
