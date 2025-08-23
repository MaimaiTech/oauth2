/**
 * User OAuth API Client
 *
 * API functions for user self-service OAuth operations in personal center
 * These endpoints use user authentication, not admin permissions
 */

import type {
  OAuthProvider,
  UserOAuthAccount,
  OAuthProviderName,
  ProviderOption,
  OAuthStatus,
  OAuthCallbackResponse,
} from './types'

import type { ResponseStruct } from '#/global'

/**
 * User OAuth Types
 */
export interface UserOAuthBinding extends Omit<UserOAuthAccount, 'access_token' | 'refresh_token' | 'raw_data'> {
  /** Provider configuration for display */
  provider_config: {
    display_name: string
    icon: string
    brand_color: string
    enabled: boolean
  }
  /** Token expiry status */
  token_expired: boolean
  /** Can unbind flag */
  can_unbind: boolean
}

export interface AvailableProvider {
  name: OAuthProviderName
  display_name: string
  icon: string
  brand_color: string
  enabled: boolean
  is_bound: boolean
  auth_url?: string
}

export interface BindingResult {
  success: boolean
  message: string
  binding?: UserOAuthBinding
}

export interface UnbindResult {
  success: boolean
  message: string
  provider: OAuthProviderName
  unbound_at: string
}

export interface OAuthCallbackResult {
  success: boolean
  message: string
  binding?: UserOAuthBinding
  redirect_url?: string
}

/**
 * API Response Types
 */
export type UserBindingsResponse = ResponseStruct<UserOAuthBinding[]>
export type AvailableProvidersResponse = ResponseStruct<AvailableProvider[]>
export type BindingResponse = ResponseStruct<BindingResult>
export type UnbindResponse = ResponseStruct<UnbindResult>
export type AuthorizeResponse = ResponseStruct<{ auth_url: string }>

/**
 * Get current user's OAuth bindings
 */
export function getCurrentUserBindings(): Promise<UserBindingsResponse> {
  return useHttp().get('/oauth/bindings')
}

/**
 * Get available OAuth providers for binding
 */
export function getAvailableProviders(): Promise<AvailableProvidersResponse> {
  return useHttp().get('/oauth/providers')
}

/**
 * Start OAuth authorization flow for a provider
 */
export function authorizeProvider(provider: OAuthProviderName, redirectUrl?: string): Promise<AuthorizeResponse> {
  const params = redirectUrl ? { redirect_url: redirectUrl } : {}
  return useHttp().get(`/oauth/authorize/${provider}`, { params })
}

/**
 * Handle OAuth callback and complete binding
 */
export function handleOAuthCallback(
  provider: OAuthProviderName,
  code: string,
  state: string
): Promise<OAuthCallbackResponse> {
  return useHttp().get(`/oauth/callback/${provider}`, {
    params: { code, state }
  })
}

/**
 * Process OAuth callback with automatic provider detection
 */
export function processOAuthCallback(params: {
  code: string
  state: string
  error?: string
  error_description?: string
}): Promise<OAuthCallbackResponse> {
  return useHttp().post('/oauth/callback', params)
}

/**
 * Bind OAuth account (after successful authorization)
 */
export function bindAccount(provider: OAuthProviderName, authCode: string): Promise<BindingResponse> {
  return useHttp().post(`/oauth/bind/${provider}`, { code: authCode })
}

/**
 * Unbind OAuth account
 */
export function unbindAccount(provider: OAuthProviderName): Promise<UnbindResponse> {
  return useHttp().delete(`/oauth/unbind/${provider}`)
}

/**
 * Refresh OAuth token (if supported by provider)
 */
export function refreshToken(provider: OAuthProviderName): Promise<BindingResponse> {
  return useHttp().post(`/oauth/refresh/${provider}`)
}

/**
 * Check OAuth binding status for specific provider
 */
export function checkBindingStatus(provider: OAuthProviderName): Promise<ResponseStruct<{
  is_bound: boolean
  binding?: UserOAuthBinding
}>> {
  return useHttp().get(`/oauth/status/${provider}`)
}

/**
 * Utility Functions
 */

/**
 * Get OAuth providers configuration with utility functions
 */
export function getOAuthProviders(): Record<OAuthProviderName, ProviderOption> {
  return {
    dingtalk: {
      value: 'dingtalk',
      label: '钉钉',
      icon: 'icon-dingtalk',
      brand_color: '#0089ff',
      default_scopes: ['openid'],
      supports_refresh_token: true,
    },
    github: {
      value: 'github',
      label: 'GitHub',
      icon: 'icon-github',
      brand_color: '#333',
      default_scopes: ['user:email'],
      supports_refresh_token: false,
    },
    gitee: {
      value: 'gitee',
      label: 'Gitee',
      icon: 'icon-gitee',
      brand_color: '#c71c27',
      default_scopes: ['user_info'],
      supports_refresh_token: true,
    },
    feishu: {
      value: 'feishu',
      label: '飞书',
      icon: 'icon-feishu',
      brand_color: '#00d4aa',
      default_scopes: ['contact:user.id:read'],
      supports_refresh_token: true,
    },
    wechat: {
      value: 'wechat',
      label: '微信',
      icon: 'icon-wechat',
      brand_color: '#07c160',
      default_scopes: ['snsapi_userinfo'],
      supports_refresh_token: true,
    },
    qq: {
      value: 'qq',
      label: 'QQ',
      icon: 'icon-qq',
      brand_color: '#12b7f5',
      default_scopes: ['get_user_info'],
      supports_refresh_token: true,
    },
  }
}

/**
 * Get provider configuration
 */
export function getProviderConfig(provider: OAuthProviderName): ProviderOption {
  const providers = getOAuthProviders()
  return providers[provider]
}

/**
 * Check if provider supports refresh token
 */
export function supportsRefreshToken(provider: OAuthProviderName): boolean {
  return getProviderConfig(provider).supports_refresh_token
}

/**
 * Get provider brand color
 */
export function getProviderColor(provider: OAuthProviderName): string {
  return getProviderConfig(provider).brand_color
}

/**
 * Get provider icon
 */
export function getProviderIcon(provider: OAuthProviderName): string {
  return getProviderConfig(provider).icon
}

/**
 * Get provider display name
 */
export function getProviderName(provider: OAuthProviderName): string {
  return getProviderConfig(provider).label
}

/**
 * Format binding for display
 */
export function formatBinding(binding: UserOAuthBinding): {
  provider_name: string
  provider_icon: string
  provider_color: string
  status_text: string
  status_color: string
  can_refresh: boolean
  last_login_text: string
} {
  const config = getProviderConfig(binding.provider)

  let statusText = '已连接'
  let statusColor = 'success'

  if (binding.status === 'disabled') {
    statusText = '已禁用'
    statusColor = 'warning'
  } else if (binding.token_expired) {
    statusText = '令牌过期'
    statusColor = 'danger'
  }

  const lastLoginText = binding.last_login_at
    ? `最后登录: ${formatDate(binding.last_login_at)}`
    : '未使用过'

  return {
    provider_name: config.label,
    provider_icon: config.icon,
    provider_color: config.brand_color,
    status_text: statusText,
    status_color: statusColor,
    can_refresh: config.supports_refresh_token && binding.status === 'normal',
    last_login_text: lastLoginText,
  }
}

/**
 * Format date for display
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return '今天'
  } else if (diffDays === 1) {
    return '昨天'
  } else if (diffDays < 7) {
    return `${diffDays}天前`
  } else {
    return date.toLocaleDateString('zh-CN')
  }
}

/**
 * Generate OAuth state parameter for CSRF protection
 */
export function generateOAuthState(): string {
  return Math.random().toString(36).substring(2, 15) +
         Math.random().toString(36).substring(2, 15)
}

/**
 * Validate OAuth state parameter
 */
export function validateOAuthState(state: string, expectedState: string): boolean {
  return state === expectedState && state.length >= 20
}

/**
 * Store OAuth state in localStorage
 */
export function storeOAuthState(provider: OAuthProviderName, state: string): void {
  localStorage.setItem(`oauth_state_${provider}`, state)
  // Auto-cleanup after 10 minutes
  setTimeout(() => {
    localStorage.removeItem(`oauth_state_${provider}`)
  }, 10 * 60 * 1000)
}

/**
 * Retrieve OAuth state from localStorage
 */
export function retrieveOAuthState(provider: OAuthProviderName): string | null {
  return localStorage.getItem(`oauth_state_${provider}`)
}

/**
 * Clear OAuth state from localStorage
 */
export function clearOAuthState(provider: OAuthProviderName): void {
  localStorage.removeItem(`oauth_state_${provider}`)
}
