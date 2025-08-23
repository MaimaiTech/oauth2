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

use App\Service\IService;
use Hyperf\Collection\Collection;
use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;
use Plugin\MaimaiTech\OAuth2\Model\Enums\OAuthProviderEnum;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;
use Plugin\MaimaiTech\OAuth2\Repository\OAuthProviderRepository;

/**
 * @extends IService<OAuthProvider>
 */
final class ProviderService extends IService
{
    public function __construct(
        protected readonly OAuthProviderRepository $repository
    ) {}

    public function getRepository(): OAuthProviderRepository
    {
        return $this->repository;
    }

    /**
     * Get all enabled OAuth providers.
     */
    public function getEnabledProviders(): Collection
    {
        return $this->repository->getEnabledProviders();
    }

    /**
     * Get provider by name.
     */
    public function getProvider(string $name): ?OAuthProvider
    {
        return $this->repository->findByName($name);
    }

    /**
     * Get provider by ID.
     */
    public function getById(int $id): ?OAuthProvider
    {
        return $this->findById($id);
    }

    /**
     * Update provider by ID.
     */
    public function update(int $id, array $data): ?OAuthProvider
    {
        return $this->updateById($id, $data);
    }

    /**
     * Delete provider by ID.
     */
    public function delete(int $id): bool
    {
        return $this->deleteProvider($id);
    }

    /**
     * Toggle provider status by ID.
     */
    public function toggle(int $id, bool $enabled): ?OAuthProvider
    {
        $provider = $this->findById($id);
        if (! $provider) {
            throw OAuthException::configurationError("Provider not found with ID: {$id}");
        }

        // If enabling, test configuration first
        if ($enabled && ! $provider->isEnabled()) {
            $this->testProviderConfiguration($provider);
        }

        $this->repository->toggleEnabled($provider->id, $enabled);

        // Return updated provider
        return $this->findById($id);
    }

    /**
     * Test provider connection by ID.
     */
    public function testConnection(int $id): array
    {
        $provider = $this->findById($id);
        if (! $provider) {
            throw OAuthException::configurationError("Provider not found with ID: {$id}");
        }

        return OAuthClientFactory::testClientCreation($provider);
    }

    /**
     * Create or update OAuth provider configuration.
     *
     * @throws OAuthException
     */
    public function createOrUpdateProvider(array $data): OAuthProvider
    {
        // Validate provider name
        if (empty($data['name'])) {
            throw OAuthException::configurationError('Provider name is required');
        }

        // Check if provider is supported
        if (! OAuthClientFactory::isProviderSupported($data['name'])) {
            throw OAuthException::configurationError(
                "Provider '{$data['name']}' is not supported",
                $data['name']
            );
        }

        // Validate required fields
        $this->validateProviderData($data);

        // Create or update provider
        $provider = $this->repository->createOrUpdate($data);

        // Test configuration
        if ($provider->isEnabled()) {
            $this->testProviderConfiguration($provider);
        }

        return $provider;
    }

    /**
     * Enable or disable provider.
     *
     * @throws OAuthException
     */
    public function toggleProvider(string $name, bool $enabled): bool
    {
        $provider = $this->getProvider($name);
        if (! $provider) {
            throw OAuthException::configurationError("Provider not found: {$name}", $name);
        }

        // If enabling, test configuration first
        if ($enabled && ! $provider->isEnabled()) {
            $this->testProviderConfiguration($provider);
        }

        return $this->repository->toggleEnabled($provider->id, $enabled);
    }

    /**
     * Test provider configuration.
     *
     * @throws OAuthException
     */
    public function testProviderConfiguration(OAuthProvider $provider): bool
    {
        return OAuthClientFactory::validateProvider($provider);
    }

    /**
     * Test provider connection.
     *
     * @throws OAuthException
     */
    public function testProviderConnection(string $name): array
    {
        $provider = $this->getProvider($name);
        if (! $provider) {
            throw OAuthException::configurationError("Provider not found: {$name}", $name);
        }

        return OAuthClientFactory::testClientCreation($provider);
    }

    /**
     * Get providers with statistics.
     */
    public function getProvidersWithStats(): Collection
    {
        return $this->repository->getProvidersWithStats();
    }

    /**
     * Get providers for UI select dropdown.
     */
    public function getProvidersForSelect(): Collection
    {
        return $this->repository->getProvidersForSelect();
    }

    /**
     * Search providers by keyword.
     */
    public function searchProviders(string $keyword, int $limit = 10): Collection
    {
        return $this->repository->searchProviders($keyword, $limit);
    }

    /**
     * Update provider sort order.
     */
    public function updateProviderSortOrder(array $sortData): bool
    {
        return $this->repository->updateSortOrder($sortData);
    }

    /**
     * Get provider information including documentation and requirements.
     */
    public function getProviderInfo(string $name): array
    {
        $info = OAuthClientFactory::getProviderInfo($name);

        // Add current configuration if provider exists
        $provider = $this->getProvider($name);
        if ($provider) {
            $info['current_config'] = [
                'id' => $provider->id,
                'enabled' => $provider->isEnabled(),
                'display_name' => $provider->display_name,
                'client_id' => $provider->client_id,
                'has_client_secret' => ! empty($provider->client_secret),
                'redirect_uri' => $provider->redirect_uri,
                'scopes' => $provider->getEffectiveScopes(),
                'created_at' => $provider->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $provider->updated_at->format('Y-m-d H:i:s'),
            ];

            // Add statistics
            $info['statistics'] = $provider->getStats();
        }

        return $info;
    }

    /**
     * Get all available providers information.
     */
    public function getAllProvidersInfo(): array
    {
        $allInfo = OAuthClientFactory::getAllProvidersInfo();

        // Enhance with current configurations
        foreach ($allInfo as $name => &$info) {
            $provider = $this->getProvider($name);
            if ($provider) {
                $info['is_configured'] = true;
                $info['is_enabled'] = $provider->isEnabled();
                $info['statistics'] = $provider->getStats();
            } else {
                $info['is_configured'] = false;
                $info['is_enabled'] = false;
            }
        }

        return $allInfo;
    }

    /**
     * Get system statistics.
     */
    public function getSystemStats(): array
    {
        $stats = $this->repository->getOverallStats();

        // Add additional information
        $stats['supported_providers'] = \count(OAuthClientFactory::getAvailableProviders());
        $stats['configured_providers'] = $stats['total_providers'];
        $stats['unconfigured_providers'] = $stats['supported_providers'] - $stats['configured_providers'];

        return $stats;
    }

    /**
     * Initialize default providers.
     */
    public function initializeDefaultProviders(): array
    {
        $results = [];
        $supportedProviders = OAuthProviderEnum::getAllProviders();

        foreach ($supportedProviders as $providerEnum) {
            $name = $providerEnum->value;

            // Skip if already exists
            if ($this->getProvider($name)) {
                $results[$name] = 'already_exists';
                continue;
            }

            try {
                $this->createOrUpdateProvider([
                    'name' => $name,
                    'display_name' => $providerEnum->getDisplayName(),
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => '',
                    'scopes' => $providerEnum->getDefaultScopes(),
                    'enabled' => false, // Disabled by default
                    'status' => 1,
                ]);

                $results[$name] = 'created';
            } catch (\Throwable $e) {
                $results[$name] = 'failed: ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Delete provider configuration.
     *
     * @throws OAuthException
     */
    public function deleteProvider(int $id): bool
    {
        $provider = $this->findById($id);
        if (! $provider) {
            throw OAuthException::configurationError('Provider not found');
        }

        // Check if provider has active bindings
        $stats = $provider->getStats();
        if ($stats['active_bindings'] > 0) {
            throw OAuthException::configurationError(
                'Cannot delete provider with active user bindings',
                $provider->name
            );
        }

        return (bool) $this->deleteById($id);
    }

    /**
     * Export provider configurations.
     */
    public function exportProviderConfigurations(bool $includeSecrets = false): array
    {
        $providers = $this->repository->list();

        return $providers->map(static function (OAuthProvider $provider) use ($includeSecrets) {
            $config = [
                'name' => $provider->name,
                'display_name' => $provider->display_name,
                'client_id' => $provider->client_id,
                'redirect_uri' => $provider->redirect_uri,
                'scopes' => $provider->getEffectiveScopes(),
                'extra_config' => $provider->extra_config,
                'enabled' => $provider->enabled,
                'status' => $provider->status->value,
                'sort' => $provider->sort,
            ];

            if ($includeSecrets) {
                $config['client_secret'] = $provider->client_secret;
            }

            return $config;
        })->toArray();
    }

    /**
     * Import provider configurations.
     *
     * @throws OAuthException
     */
    public function importProviderConfigurations(array $configurations, bool $overwrite = false): array
    {
        $results = [];

        foreach ($configurations as $config) {
            $name = $config['name'] ?? '';

            try {
                if (empty($name)) {
                    throw new \InvalidArgumentException('Provider name is required');
                }

                $existing = $this->getProvider($name);
                if ($existing && ! $overwrite) {
                    $results[$name] = 'skipped';
                    continue;
                }

                $this->createOrUpdateProvider($config);
                $results[$name] = $existing ? 'updated' : 'created';
            } catch (\Throwable $e) {
                $results[$name] = 'failed: ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Validate provider configuration data.
     *
     * @throws OAuthException
     */
    private function validateProviderData(array $data): void
    {
        $required = ['name', 'client_id', 'client_secret', 'redirect_uri'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw OAuthException::configurationError("Missing required field: {$field}");
            }
        }

        // Validate redirect URI format
        if (! filter_var($data['redirect_uri'], \FILTER_VALIDATE_URL)) {
            throw OAuthException::configurationError('Invalid redirect_uri format');
        }

        // Validate provider name against enum
        try {
            OAuthProviderEnum::from($data['name']);
        } catch (\ValueError $e) {
            throw OAuthException::configurationError("Invalid provider name: {$data['name']}");
        }
    }
}
