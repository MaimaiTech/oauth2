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
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthStateStatusEnum;

use function Hyperf\Support\now;

/**
 * @property int $id 主键
 * @property string $state 状态参数(随机字符串)
 * @property string $provider OAuth服务商
 * @property null|int $user_id 用户ID(可为空,支持匿名OAuth流程)
 * @property null|array $payload 附加数据
 * @property null|string $client_ip 客户端IP
 * @property null|string $user_agent 用户代理
 * @property Carbon $expires_at 过期时间
 * @property OAuthStateStatusEnum $status 状态
 * @property null|Carbon $used_at 使用时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property null|User $user 关联用户
 */
final class OAuthState extends Model
{
    /**
     * Default state expiration time in minutes.
     */
    public const DEFAULT_EXPIRATION_MINUTES = 15;

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'oauth_states';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'id',
        'state',
        'provider',
        'user_id',
        'payload',
        'client_ip',
        'user_agent',
        'expires_at',
        'status',
        'used_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'payload' => 'json',
        'expires_at' => 'datetime',
        'status' => OAuthStateStatusEnum::class,
        'used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the OAuth state.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the provider enum instance.
     */
    public function getProviderEnum(): ?OAuthProviderEnum
    {
        return OAuthProviderEnum::tryFrom($this->provider);
    }

    /**
     * Check if the state is valid.
     */
    public function isValid(): bool
    {
        return $this->status->isValid() && ! $this->isExpired();
    }

    /**
     * Check if the state is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the state has been used.
     */
    public function isUsed(): bool
    {
        return $this->status->isUsed();
    }

    /**
     * Check if the state can be consumed.
     */
    public function canConsume(): bool
    {
        return $this->isValid() && ! $this->isUsed();
    }

    /**
     * Mark the state as used.
     */
    public function markAsUsed(): void
    {
        $this->update([
            'status' => OAuthStateStatusEnum::USED,
            'used_at' => now(),
        ]);
    }

    /**
     * Mark the state as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => OAuthStateStatusEnum::EXPIRED,
        ]);
    }

    /**
     * Create a new OAuth state.
     */
    public static function createState(
        string $provider,
        ?int $userId = null,
        array $payload = [],
        ?string $clientIp = null,
        ?string $userAgent = null,
        ?int $expirationMinutes = null
    ): self {
        $expirationMinutes ??= self::DEFAULT_EXPIRATION_MINUTES;

        return self::create([
            'state' => self::generateState(),
            'provider' => $provider,
            'user_id' => $userId,
            'payload' => empty($payload) ? null : $payload,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'expires_at' => now()->addMinutes($expirationMinutes),
            'status' => OAuthStateStatusEnum::VALID,
        ]);
    }

    /**
     * Generate a cryptographically secure state parameter.
     */
    public static function generateState(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Find and validate a state parameter.
     */
    public static function findValidState(string $state, string $provider): ?self
    {
        $stateRecord = self::query()
            ->where('state', $state)
            ->where('provider', $provider)
            ->first();

        if (! $stateRecord) {
            return null;
        }

        // Check if expired and mark accordingly
        if ($stateRecord->isExpired() && $stateRecord->status->isValid()) {
            $stateRecord->markAsExpired();
            return null;
        }

        // Return only if can be consumed
        return $stateRecord->canConsume() ? $stateRecord : null;
    }

    /**
     * Clean up expired states.
     */
    public static function cleanupExpired(): int
    {
        $expiredStates = self::query()
            ->where('expires_at', '<', now())
            ->where('status', OAuthStateStatusEnum::VALID);

        $count = $expiredStates->count();

        $expiredStates->update([
            'status' => OAuthStateStatusEnum::EXPIRED,
        ]);

        return $count;
    }

    /**
     * Clean up old states (older than specified days).
     */
    public static function cleanupOldStates(int $daysOld = 30): int
    {
        return self::query()
            ->where('created_at', '<', now()->subDays($daysOld))
            ->whereIn('status', [OAuthStateStatusEnum::USED, OAuthStateStatusEnum::EXPIRED])
            ->delete();
    }

    /**
     * Get statistics for OAuth states.
     */
    public static function getStats(): array
    {
        $total = self::count();
        $valid = self::query()
            ->where('status', OAuthStateStatusEnum::VALID)
            ->where('expires_at', '>', now())
            ->count();
        $expired = self::query()
            ->where(static function ($q) {
                $q->where('status', OAuthStateStatusEnum::EXPIRED)
                    ->orWhere('expires_at', '<', now());
            })
            ->count();
        $used = self::query()
            ->where('status', OAuthStateStatusEnum::USED)
            ->count();

        return [
            'total' => $total,
            'valid' => $valid,
            'expired' => $expired,
            'used' => $used,
            'cleanup_needed' => $expired + $used,
        ];
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
     * Get time remaining before expiration.
     */
    public function getTimeRemaining(): ?int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInMinutes($this->expires_at);
    }

    /**
     * Get formatted state info for debugging.
     */
    public function getFormattedInfo(): array
    {
        return [
            'state' => $this->state,
            'provider' => $this->getProviderDisplayName(),
            'user_id' => $this->user_id,
            'status' => $this->status->name,
            'expires_in_minutes' => $this->getTimeRemaining(),
            'client_ip' => $this->client_ip,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'expires_at' => $this->expires_at->format('Y-m-d H:i:s'),
            'used_at' => $this->used_at?->format('Y-m-d H:i:s'),
        ];
    }
}
