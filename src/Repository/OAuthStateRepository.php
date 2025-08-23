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

namespace Plugin\MaimaiTech\OAuth2\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthStateStatusEnum;
use Plugin\MaimaiTech\OAuth2\Model\OAuthState;

use function Hyperf\Support\now;

/**
 * @extends IRepository<OAuthState>
 */
final class OAuthStateRepository extends IRepository
{
    public function __construct(
        protected readonly OAuthState $model
    ) {}

    /**
     * Build query for valid states.
     */
    public function queryValidStates(): Builder
    {
        return $this->model->newQuery()
            ->where('status', OAuthStateStatusEnum::VALID)
            ->where('expires_at', '>', now());
    }

    /**
     * Build query for expired states.
     */
    public function queryExpiredStates(): Builder
    {
        return $this->model->newQuery()
            ->where(static function ($q) {
                $q->where('status', OAuthStateStatusEnum::EXPIRED)
                    ->orWhere('expires_at', '<', now());
            });
    }

    /**
     * Build query for used states.
     */
    public function queryUsedStates(): Builder
    {
        return $this->model->newQuery()
            ->where('status', OAuthStateStatusEnum::USED);
    }

    /**
     * Build query filtered by provider.
     */
    public function queryByProvider(string $provider): Builder
    {
        return $this->model->newQuery()->where('provider', $provider);
    }

    /**
     * Build query filtered by user.
     */
    public function queryByUser(int $userId): Builder
    {
        return $this->model->newQuery()->where('user_id', $userId);
    }

    /**
     * Build query filtered by client IP.
     */
    public function queryByClientIp(string $clientIp): Builder
    {
        return $this->model->newQuery()->where('client_ip', $clientIp);
    }

    /**
     * Create a new OAuth state.
     */
    public function createState(
        string $provider,
        ?int $userId = null,
        array $payload = [],
        ?string $clientIp = null,
        ?string $userAgent = null,
        ?int $expirationMinutes = null
    ): OAuthState {
        return OAuthState::createState(
            $provider,
            $userId,
            $payload,
            $clientIp,
            $userAgent,
            $expirationMinutes
        );
    }

    /**
     * Validate and consume a state parameter.
     */
    public function validateAndConsumeState(string $state, string $provider): ?array
    {
        $stateRecord = OAuthState::findValidState($state, $provider);

        if (! $stateRecord) {
            return null;
        }

        // Mark as used
        $stateRecord->markAsUsed();

        // Return state data
        return [
            'user_id' => $stateRecord->user_id,
            'provider' => $stateRecord->provider,
            'payload' => $stateRecord->payload,
            'client_ip' => $stateRecord->client_ip,
            'expires_at' => $stateRecord->expires_at,
            'created_at' => $stateRecord->created_at,
        ];
    }

    /**
     * Find valid state without consuming it.
     */
    public function findValidState(string $state, string $provider): ?OAuthState
    {
        return OAuthState::findValidState($state, $provider);
    }

    /**
     * Clean up expired states.
     */
    public function cleanupExpiredStates(): int
    {
        return OAuthState::cleanupExpired();
    }

    /**
     * Clean up old states (used/expired older than specified days).
     */
    public function cleanupOldStates(int $daysOld = 30): int
    {
        return OAuthState::cleanupOldStates($daysOld);
    }

    /**
     * Get states by user.
     */
    public function getUserStates(int $userId, ?string $provider = null): Collection
    {
        $query = $this->queryByUser($userId);

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get states by provider.
     */
    public function getProviderStates(string $provider, ?string $status = null): Collection
    {
        $query = $this->queryByProvider($provider);

        if ($status) {
            $statusEnum = OAuthStateStatusEnum::from($status);
            $query->where('status', $statusEnum);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get valid states count.
     */
    public function getValidStatesCount(): int
    {
        return $this->queryValidStates()->count();
    }

    /**
     * Get expired states count.
     */
    public function getExpiredStatesCount(): int
    {
        return $this->queryExpiredStates()->count();
    }

    /**
     * Get used states count.
     */
    public function getUsedStatesCount(): int
    {
        return $this->queryUsedStates()->count();
    }

    /**
     * Get states statistics.
     */
    public function getStatesStats(): array
    {
        return OAuthState::getStats();
    }

    /**
     * Get states by IP address for security monitoring.
     */
    public function getStatesByIp(string $clientIp, int $limit = 50): Collection
    {
        return $this->queryByClientIp($clientIp)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent state activities.
     */
    public function getRecentStateActivities(int $limit = 100): Collection
    {
        return $this->model->newQuery()
            ->with('user:id,username,nickname')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get states that will expire soon.
     */
    public function getStatesExpiringSoon(int $minutesAhead = 5): Collection
    {
        $expiryThreshold = now()->addMinutes($minutesAhead);

        return $this->model->newQuery()
            ->where('status', OAuthStateStatusEnum::VALID)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', $expiryThreshold)
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Count states by user in a time period (rate limiting check).
     */
    public function countUserStatesInPeriod(
        int $userId,
        string $provider,
        int $minutes = 15
    ): int {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Count states by IP in a time period (rate limiting check).
     */
    public function countIpStatesInPeriod(
        string $clientIp,
        string $provider,
        int $minutes = 15
    ): int {
        return $this->model->newQuery()
            ->where('client_ip', $clientIp)
            ->where('provider', $provider)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Check if user has exceeded state creation rate limit.
     */
    public function hasUserExceededRateLimit(
        int $userId,
        string $provider,
        int $maxStatesPerPeriod = 10,
        int $periodMinutes = 15
    ): bool {
        $count = $this->countUserStatesInPeriod($userId, $provider, $periodMinutes);
        return $count >= $maxStatesPerPeriod;
    }

    /**
     * Check if IP has exceeded state creation rate limit.
     */
    public function hasIpExceededRateLimit(
        string $clientIp,
        string $provider,
        int $maxStatesPerPeriod = 20,
        int $periodMinutes = 15
    ): bool {
        $count = $this->countIpStatesInPeriod($clientIp, $provider, $periodMinutes);
        return $count >= $maxStatesPerPeriod;
    }

    /**
     * Get states statistics by provider.
     */
    public function getStatsGroupedByProvider(): Collection
    {
        return $this->model->newQuery()
            ->selectRaw('provider, COUNT(*) as total_states')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as valid_states', [OAuthStateStatusEnum::VALID->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as used_states', [OAuthStateStatusEnum::USED->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as expired_states', [OAuthStateStatusEnum::EXPIRED->value])
            ->groupBy('provider')
            ->orderBy('total_states', 'desc')
            ->get();
    }

    /**
     * Get suspicious activities (high frequency state creation).
     */
    public function getSuspiciousActivities(int $thresholdPerHour = 50): Collection
    {
        return $this->model->newQuery()
            ->selectRaw('client_ip, provider, COUNT(*) as state_count')
            ->where('created_at', '>=', now()->subHour())
            ->groupBy(['client_ip', 'provider'])
            ->having('state_count', '>=', $thresholdPerHour)
            ->orderBy('state_count', 'desc')
            ->get();
    }

    /**
     * Force expire states by criteria.
     */
    public function forceExpireStates(array $criteria): int
    {
        $query = $this->model->newQuery()->where('status', OAuthStateStatusEnum::VALID);

        // Apply criteria
        if (isset($criteria['provider'])) {
            $query->where('provider', $criteria['provider']);
        }

        if (isset($criteria['user_id'])) {
            $query->where('user_id', $criteria['user_id']);
        }

        if (isset($criteria['client_ip'])) {
            $query->where('client_ip', $criteria['client_ip']);
        }

        if (isset($criteria['created_before'])) {
            $query->where('created_at', '<', $criteria['created_before']);
        }

        $count = $query->count();

        $query->update([
            'status' => OAuthStateStatusEnum::EXPIRED,
            'updated_at' => now(),
        ]);

        return $count;
    }

    /**
     * Handle search parameters for filtering.
     */
    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'state'), static function (Builder $query, $state) {
                $query->where('state', 'like', "%{$state}%");
            })
            ->when(Arr::get($params, 'provider'), static function (Builder $query, $provider) {
                if (\is_array($provider)) {
                    $query->whereIn('provider', $provider);
                } else {
                    $query->where('provider', $provider);
                }
            })
            ->when(Arr::get($params, 'user_id'), static function (Builder $query, $userId) {
                if (\is_array($userId)) {
                    $query->whereIn('user_id', $userId);
                } else {
                    $query->where('user_id', $userId);
                }
            })
            ->when(Arr::get($params, 'client_ip'), static function (Builder $query, $clientIp) {
                $query->where('client_ip', $clientIp);
            })
            ->when(Arr::get($params, 'status'), static function (Builder $query, $status) {
                if (\is_array($status)) {
                    $query->whereIn('status', $status);
                } else {
                    $query->where('status', $status);
                }
            })
            ->when(Arr::get($params, 'expires_at'), static function (Builder $query, $expiresAt) {
                if (\is_array($expiresAt) && \count($expiresAt) === 2) {
                    $query->whereBetween('expires_at', $expiresAt);
                }
            })
            ->when(Arr::get($params, 'created_at'), static function (Builder $query, $createdAt) {
                if (\is_array($createdAt) && \count($createdAt) === 2) {
                    $query->whereBetween('created_at', $createdAt);
                }
            })
            ->when(Arr::get($params, 'used_at'), static function (Builder $query, $usedAt) {
                if (\is_array($usedAt) && \count($usedAt) === 2) {
                    $query->whereBetween('used_at', $usedAt);
                }
            })
            ->when(Arr::get($params, 'only_valid'), static function (Builder $query) {
                $query->where('status', OAuthStateStatusEnum::VALID)
                    ->where('expires_at', '>', now());
            })
            ->when(Arr::get($params, 'only_expired'), static function (Builder $query) {
                $query->where(static function ($q) {
                    $q->where('status', OAuthStateStatusEnum::EXPIRED)
                        ->orWhere('expires_at', '<', now());
                });
            })
            ->when(Arr::get($params, 'only_used'), static function (Builder $query) {
                $query->where('status', OAuthStateStatusEnum::USED);
            })
            ->when(Arr::get($params, 'with_user'), static function (Builder $query) {
                $query->with('user:id,username,nickname');
            })
            ->when(Arr::get($params, 'sortable'), static function (Builder $query, array $sortable) {
                $query->orderBy(key($sortable), current($sortable));
            }, static function (Builder $query) {
                // Default ordering
                $query->orderBy('created_at', 'desc');
            });
    }

    /**
     * Batch cleanup operations.
     */
    public function performMaintenance(): array
    {
        $expiredCount = $this->cleanupExpiredStates();
        $oldStatesCount = $this->cleanupOldStates();
        $stats = $this->getStatesStats();

        return [
            'expired_states_cleaned' => $expiredCount,
            'old_states_cleaned' => $oldStatesCount,
            'remaining_stats' => $stats,
            'maintenance_performed_at' => now()->toISOString(),
        ];
    }
}
