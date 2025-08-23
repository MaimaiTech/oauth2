/**
 * OAuth2 Plugin API Client
 * 
 * HTTP client functions for OAuth2 providers and user bindings management
 * Uses MineAdmin's useHttp() hook for consistent API communication
 */

import type {
  OAuthProvider,
  CreateProviderRequest,
  UpdateProviderRequest,
  ProviderQueryParams,
  ProviderToggleRequest,
  ProviderListResponse,
  ProviderResponse,
  TestConnectionResponse,
  UserOAuthAccount,
  UserBindingsQueryParams,
  UserBindingsListResponse,
  UserBindingsResponse,
  ForceUnbindResponse,
  StatisticsResponse,
  BatchOperationRequest,
  BatchOperationResponse,
  ExportRequest,
  ExportResponse,
  ProviderOption,
  OAuthProviderName,
} from './types'

/**
 * OAuth Provider Management APIs
 */

/**
 * Get paginated list of OAuth providers
 */
export function getProviders(params?: ProviderQueryParams): Promise<ProviderListResponse> {
  return useHttp().get('/admin/oauth/providers', { params })
}

/**
 * Get single OAuth provider by ID
 */
export function getProvider(id: number): Promise<ProviderResponse> {
  return useHttp().get(`/admin/oauth/providers/${id}`)
}

/**
 * Create new OAuth provider
 */
export function createProvider(data: CreateProviderRequest): Promise<ProviderResponse> {
  return useHttp().post('/admin/oauth/providers', data)
}

/**
 * Update existing OAuth provider
 */
export function updateProvider(id: number, data: UpdateProviderRequest): Promise<ProviderResponse> {
  return useHttp().put(`/admin/oauth/providers/${id}`, data)
}

/**
 * Delete OAuth provider
 */
export function deleteProvider(id: number): Promise<ProviderResponse> {
  return useHttp().delete(`/admin/oauth/providers/${id}`)
}

/**
 * Batch delete OAuth providers
 */
export function deleteProviders(ids: number[]): Promise<ProviderResponse> {
  return useHttp().delete('/admin/oauth/providers', { data: ids })
}

/**
 * Toggle OAuth provider enabled status
 */
export function toggleProvider(id: number, enabled: boolean): Promise<ProviderResponse> {
  return useHttp().post(`/admin/oauth/providers/${id}/toggle`, { enabled })
}

/**
 * Test OAuth provider connection
 */
export function testProvider(id: number): Promise<TestConnectionResponse> {
  return useHttp().post(`/admin/oauth/providers/${id}/test`)
}

/**
 * User OAuth Bindings Management APIs
 */

/**
 * Get paginated list of all user OAuth bindings
 */
export function getUserBindings(params?: UserBindingsQueryParams): Promise<UserBindingsListResponse> {
  return useHttp().get('/admin/oauth/bindings', { params })
}

/**
 * Get OAuth bindings for specific user
 */
export function getUserBindingsByUserId(userId: number): Promise<UserBindingsResponse> {
  return useHttp().get(`/admin/oauth/bindings/user/${userId}`)
}

/**
 * Force unbind user OAuth account (admin operation)
 */
export function forceUnbindAccount(id: number): Promise<ForceUnbindResponse> {
  return useHttp().delete(`/admin/oauth/bindings/${id}`)
}

/**
 * Get OAuth binding statistics
 */
export function getBindingStatistics(period: 'day' | 'week' | 'month' | 'year' = 'month'): Promise<StatisticsResponse> {
  return useHttp().get('/admin/oauth/bindings/statistics', { params: { period } })
}

/**
 * Batch operate on OAuth bindings
 */
export function batchOperateBindings(data: BatchOperationRequest): Promise<BatchOperationResponse> {
  return useHttp().post('/admin/oauth/bindings/batch', data)
}

/**
 * Export OAuth bindings data
 */
export function exportBindings(params: ExportRequest): Promise<ExportResponse> {
  return useHttp().get('/admin/oauth/bindings/export', { params })
}

/**
 * Utility Functions
 */

/**
 * Get provider options for select components
 */
export function getProviderOptions(): ProviderOption[] {
  return [
    {
      value: 'dingtalk',
      label: '钉钉',
      icon: 'icon-dingtalk',
      brand_color: '#007aff',
      default_scopes: ['openid'],
      supports_refresh_token: true,
    },
    {
      value: 'github',
      label: 'GitHub',
      icon: 'icon-github',
      brand_color: '#24292e',
      default_scopes: ['user:email'],
      supports_refresh_token: false,
    },
    {
      value: 'gitee',
      label: '码云',
      icon: 'icon-gitee',
      brand_color: '#c71d23',
      default_scopes: ['user_info'],
      supports_refresh_token: true,
    },
    {
      value: 'feishu',
      label: '飞书',
      icon: 'icon-feishu',
      brand_color: '#00d4aa',
      default_scopes: ['contact:user.id:read'],
      supports_refresh_token: true,
    },
    {
      value: 'wechat',
      label: '微信',
      icon: 'icon-wechat',
      brand_color: '#07c160',
      default_scopes: ['snsapi_userinfo'],
      supports_refresh_token: true,
    },
    {
      value: 'qq',
      label: 'QQ',
      icon: 'icon-qq',
      brand_color: '#12b7f5',
      default_scopes: ['get_user_info'],
      supports_refresh_token: true,
    },
  ]
}

/**
 * Get provider option by name
 */
export function getProviderOption(name: OAuthProviderName): ProviderOption | undefined {
  return getProviderOptions().find(option => option.value === name)
}

/**
 * Get provider display name
 */
export function getProviderDisplayName(name: OAuthProviderName): string {
  const option = getProviderOption(name)
  return option?.label || name
}

/**
 * Get provider icon
 */
export function getProviderIcon(name: OAuthProviderName): string {
  const option = getProviderOption(name)
  return option?.icon || 'icon-oauth'
}

/**
 * Get provider brand color
 */
export function getProviderBrandColor(name: OAuthProviderName): string {
  const option = getProviderOption(name)
  return option?.brand_color || '#666666'
}

/**
 * Get provider default scopes
 */
export function getProviderDefaultScopes(name: OAuthProviderName): string[] {
  const option = getProviderOption(name)
  return option?.default_scopes || []
}

/**
 * Check if provider supports refresh token
 */
export function providerSupportsRefreshToken(name: OAuthProviderName): boolean {
  const option = getProviderOption(name)
  return option?.supports_refresh_token || false
}

/**
 * Format provider configuration for display
 */
export function formatProviderConfig(provider: OAuthProvider): Record<string, any> {
  return {
    ...provider,
    display_name: provider.display_name || getProviderDisplayName(provider.name),
    icon: getProviderIcon(provider.name),
    brand_color: getProviderBrandColor(provider.name),
    effective_scopes: provider.scopes || getProviderDefaultScopes(provider.name),
    supports_refresh_token: providerSupportsRefreshToken(provider.name),
  }
}

/**
 * Validate provider configuration
 */
export function validateProviderConfig(provider: Partial<CreateProviderRequest>): string[] {
  const errors: string[] = []
  
  if (!provider.name) {
    errors.push('Provider name is required')
  }
  
  if (!provider.display_name) {
    errors.push('Display name is required')
  }
  
  if (!provider.client_id) {
    errors.push('Client ID is required')
  }
  
  if (!provider.client_secret) {
    errors.push('Client secret is required')
  }
  
  if (!provider.redirect_uri) {
    errors.push('Redirect URI is required')
  } else {
    try {
      new URL(provider.redirect_uri)
    } catch {
      errors.push('Redirect URI must be a valid URL')
    }
  }
  
  return errors
}