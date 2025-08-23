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
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthStatusEnum;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;

/**
 * @extends IRepository<OAuthProvider>
 */
final class OAuthProviderRepository extends IRepository
{
    public function __construct(
        protected readonly OAuthProvider $model
    ) {}

    /**
     * Find provider by name.
     */
    public function findByName(string $name): ?OAuthProvider
    {
        return $this->queryByName($name)->first();
    }

    /**
     * Build query for enabled providers.
     */
    public function queryEnabledProviders(): Builder
    {
        return $this->model->newQuery()
            ->where('enabled', true)
            ->where('status', OAuthStatusEnum::NORMAL);
    }

    /**
     * Build query ordered by sort and id.
     */
    public function queryOrdered(): Builder
    {
        return $this->model->newQuery()
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * Build query filtered by provider name.
     */
    public function queryByName(string $name): Builder
    {
        return $this->model->newQuery()->where('name', $name);
    }

    /**
     * Get all enabled providers.
     */
    public function getEnabledProviders(): Collection
    {
        return $this->queryEnabledProviders()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create or update provider configuration.
     */
    public function createOrUpdate(array $data): OAuthProvider
    {
        $provider = $this->findByName($data['name'] ?? '');

        if ($provider) {
            $provider->update($data);
            return $provider->fresh();
        }

        return $this->create($data);
    }

    /**
     * Toggle provider enabled status.
     */
    public function toggleEnabled(int $id, bool $enabled): bool
    {
        return $this->updateById($id, ['enabled' => $enabled]);
    }

    /**
     * Get provider statistics.
     */
    public function getProviderStats(int $id): array
    {
        $provider = $this->findById($id);
        return $provider?->getStats() ?? [];
    }

    /**
     * Get providers with binding counts.
     */
    public function getProvidersWithStats(): Collection
    {
        return $this->model->newQuery()
            ->withCount(['userAccounts as total_bindings'])
            ->withCount(['userAccounts as active_bindings' => static function ($query) {
                $query->where('status', OAuthStatusEnum::NORMAL);
            }])
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(static function (OAuthProvider $provider) {
                $data = $provider->toArray();
                $data['inactive_bindings'] = $data['total_bindings'] - $data['active_bindings'];
                return $data;
            });
    }

    /**
     * Check if provider name is available.
     */
    public function isNameAvailable(string $name, ?int $excludeId = null): bool
    {
        $query = $this->model->newQuery()->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return ! $query->exists();
    }

    /**
     * Get providers for dropdown/select options.
     */
    public function getProvidersForSelect(): Collection
    {
        return $this->queryEnabledProviders()
            ->select(['id', 'name', 'display_name', 'sort'])
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(static function (OAuthProvider $provider) {
                return [
                    'value' => $provider->name,
                    'label' => $provider->display_name,
                    'id' => $provider->id,
                    'icon' => $provider->getIcon(),
                    'color' => $provider->getBrandColor(),
                ];
            });
    }

    /**
     * Batch update provider sort order.
     */
    public function updateSortOrder(array $sortData): bool
    {
        foreach ($sortData as $item) {
            if (isset($item['id'], $item['sort'])) {
                $this->updateById($item['id'], ['sort' => $item['sort']]);
            }
        }
        return true;
    }

    /**
     * Handle search parameters for filtering.
     */
    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'name'), static function (Builder $query, $name) {
                if (\is_array($name)) {
                    $query->whereIn('name', $name);
                } else {
                    $query->where('name', 'like', "%{$name}%");
                }
            })
            ->when(Arr::get($params, 'display_name'), static function (Builder $query, $displayName) {
                $query->where('display_name', 'like', "%{$displayName}%");
            })
            ->when(Arr::has($params, 'enabled'), static function (Builder $query) use ($params) {
                $enabled = Arr::get($params, 'enabled');
                if ($enabled !== null) {
                    $query->where('enabled', (bool) $enabled);
                }
            })
            ->when(Arr::get($params, 'status'), static function (Builder $query, $status) {
                if (\is_array($status)) {
                    $query->whereIn('status', $status);
                } else {
                    $query->where('status', $status);
                }
            })
            ->when(Arr::get($params, 'provider_names'), static function (Builder $query, $providerNames) {
                $query->whereIn('name', Arr::wrap($providerNames));
            })
            ->when(Arr::get($params, 'created_by'), static function (Builder $query, $createdBy) {
                $query->where('created_by', $createdBy);
            })
            ->when(Arr::get($params, 'updated_by'), static function (Builder $query, $updatedBy) {
                $query->where('updated_by', $updatedBy);
            })
            ->when(Arr::get($params, 'created_at'), static function (Builder $query, $createdAt) {
                if (\is_array($createdAt) && \count($createdAt) === 2) {
                    $query->whereBetween('created_at', $createdAt);
                }
            })
            ->when(Arr::get($params, 'updated_at'), static function (Builder $query, $updatedAt) {
                if (\is_array($updatedAt) && \count($updatedAt) === 2) {
                    $query->whereBetween('updated_at', $updatedAt);
                }
            })
            ->when(
                Arr::get($params, 'sortable'),
                static function (Builder $query, array $sortable) {
                    $query->orderBy(key($sortable), current($sortable));
                },
                static function (Builder $query) {
                    // Default ordering
                    $query->orderBy('sort')->orderBy('id');
                }
            );
    }

    /**
     * Get enabled providers count.
     */
    public function getEnabledProvidersCount(): int
    {
        return $this->queryEnabledProviders()->count();
    }

    /**
     * Get disabled providers count.
     */
    public function getDisabledProvidersCount(): int
    {
        return $this->model->newQuery()
            ->where(static function ($query) {
                $query->where('enabled', false)
                    ->orWhere('status', OAuthStatusEnum::DISABLED);
            })
            ->count();
    }

    /**
     * Get providers summary statistics.
     */
    public function getOverallStats(): array
    {
        $total = $this->count();
        $enabled = $this->getEnabledProvidersCount();
        $disabled = $this->getDisabledProvidersCount();

        // Get total bindings across all providers
        $totalBindings = $this->model->newQuery()
            ->withCount('userAccounts')
            ->get()
            ->sum('user_accounts_count');

        // Get active bindings across all providers
        $activeBindings = $this->model->newQuery()
            ->withCount(['userAccounts as active_bindings' => static function ($query) {
                $query->where('status', OAuthStatusEnum::NORMAL);
            }])
            ->get()
            ->sum('active_bindings');

        return [
            'total_providers' => $total,
            'enabled_providers' => $enabled,
            'disabled_providers' => $disabled,
            'total_bindings' => $totalBindings,
            'active_bindings' => $activeBindings,
            'inactive_bindings' => $totalBindings - $activeBindings,
        ];
    }

    /**
     * Search providers by keyword.
     */
    public function searchProviders(string $keyword, int $limit = 10): Collection
    {
        return $this->model->newQuery()
            ->where(static function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                    ->orWhere('display_name', 'like', "%{$keyword}%");
            })
            ->where('enabled', true)
            ->where('status', OAuthStatusEnum::NORMAL)
            ->orderBy('sort')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently updated providers.
     */
    public function getRecentlyUpdated(int $limit = 5): Collection
    {
        return $this->model->newQuery()
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
