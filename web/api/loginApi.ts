/**
 * OAuth Login API Client
 *
 * API functions for OAuth login functionality
 * These endpoints handle authentication and login flows
 */

import type {
  OAuthProvider,
  OAuthLoginCallbackResponse,
  OAuthProviderName,
} from './types'

import type { ResponseStruct } from '#/global'

/**
 * Login API Response Types
 */
export type LoginProvidersResponse = ResponseStruct<OAuthProvider[]>
export type LoginRedirectResponse = ResponseStruct<{
  auth_url: string
  provider: OAuthProviderName
  state: string
}>

/**
 * Get available OAuth providers for login
 */
export function getLoginProviders(): Promise<LoginProvidersResponse> {
  return useHttp().get('/passport/oauth/login/providers')
}

/**
 * Initiate OAuth login flow
 */
export function initiateOAuthLogin(
  provider: OAuthProviderName,
  redirectUri?: string
): Promise<LoginRedirectResponse> {
  const data = redirectUri ? { redirect_uri: redirectUri } : {}
  return useHttp().post(`/passport/oauth/${provider}`, data)
}

/**
 * Handle OAuth login callback
 */
export function handleOAuthLoginCallback(
  provider: OAuthProviderName,
  code: string,
  state: string,
): Promise<OAuthLoginCallbackResponse> {
  return useHttp().get(`/passport/oauth/login/callback/${provider}`, {
    params: { code, state },
  })
}

/**
 * Check login provider configuration
 */
export function checkProviderConfig(provider: OAuthProviderName): Promise<ResponseStruct<{
  enabled: boolean
  configured: boolean
  display_name: string
}>> {
  return useHttp().get(`/passport/oauth/login/check/${provider}`)
}

/**
 * Utility Functions for Login UI
 */

/**
 * OAuth providers configuration for login components
 */
export function getLoginProviderConfig(): Record<OAuthProviderName, {
  name: string
  brandColor: string
  textColor: string
  icon: string
}> {
  return {
    dingtalk: {
      name: '钉钉',
      brandColor: '#0089FF',
      textColor: '#ffffff',
      icon: 'dingtalk'
    },
    github: {
      name: 'GitHub',
      brandColor: '#24292e',
      textColor: '#ffffff',
      icon: 'github'
    },
    gitee: {
      name: 'Gitee',
      brandColor: '#C71D23',
      textColor: '#ffffff',
      icon: 'gitee'
    },
    feishu: {
      name: '飞书',
      brandColor: '#00D6B9',
      textColor: '#ffffff',
      icon: 'feishu'
    },
    wechat: {
      name: '微信',
      brandColor: '#07C160',
      textColor: '#ffffff',
      icon: 'wechat'
    },
    qq: {
      name: 'QQ',
      brandColor: '#EB7350',
      textColor: '#ffffff',
      icon: 'qq'
    }
  }
}

/**
 * Get provider display configuration
 */
export function getProviderDisplayConfig(provider: OAuthProviderName) {
  const config = getLoginProviderConfig()
  return config[provider] || {
    name: provider.charAt(0).toUpperCase() + provider.slice(1),
    brandColor: '#409eff',
    textColor: '#ffffff',
    icon: 'user'
  }
}

/**
 * Validate provider support
 */
export function isProviderSupported(provider: string): provider is OAuthProviderName {
  const supportedProviders: OAuthProviderName[] = ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq']
  return supportedProviders.includes(provider as OAuthProviderName)
}

/**
 * Generate login URL for direct redirect
 */
export function generateLoginUrl(provider: OAuthProviderName): string {
  const api =  `/passport/oauth/${provider}`
  initiateOAuthLogin(provider)

}

/**
 * Store login state for security
 */
export function storeLoginState(provider: OAuthProviderName, state: string): void {
  sessionStorage.setItem(`oauth_login_state_${provider}`, state)

  // Auto-cleanup after 10 minutes
  setTimeout(() => {
    sessionStorage.removeItem(`oauth_login_state_${provider}`)
  }, 10 * 60 * 1000)
}

/**
 * Retrieve login state
 */
export function retrieveLoginState(provider: OAuthProviderName): string | null {
  return sessionStorage.getItem(`oauth_login_state_${provider}`)
}

/**
 * Clear login state
 */
export function clearLoginState(provider: OAuthProviderName): void {
  sessionStorage.removeItem(`oauth_login_state_${provider}`)
}

/**
 * Store authentication tokens after successful login
 */
export function storeAuthTokens(tokens: {
  access_token: string
  refresh_token?: string
  expire_at: number
}): void {
  localStorage.setItem('access_token', tokens.access_token)

  if (tokens.refresh_token) {
    localStorage.setItem('refresh_token', tokens.refresh_token)
  }

  localStorage.setItem('token_expire_at', tokens.expire_at.toString())
}

/**
 * Store user information after successful login
 */
export function storeUserInfo(user: {
  id: number
  username: string
  nickname?: string
  email?: string
  avatar?: string
}): void {
  localStorage.setItem('user_info', JSON.stringify(user))
  localStorage.setItem('user_id', user.id.toString())
}

/**
 * Clear authentication data
 */
export function clearAuthData(): void {
  localStorage.removeItem('access_token')
  localStorage.removeItem('refresh_token')
  localStorage.removeItem('token_expire_at')
  localStorage.removeItem('user_info')
  localStorage.removeItem('user_id')
}

/**
 * Check if user is authenticated
 */
export function isAuthenticated(): boolean {
  const token = localStorage.getItem('access_token')
  const expireAt = localStorage.getItem('token_expire_at')

  if (!token || !expireAt) {
    return false
  }

  const expireTime = parseInt(expireAt, 10)
  const currentTime = Math.floor(Date.now() / 1000)

  return currentTime < expireTime
}

/**
 * Get current user info from storage
 */
export function getCurrentUser(): {
  id: number
  username: string
  nickname?: string
  email?: string
  avatar?: string
} | null {
  const userInfoStr = localStorage.getItem('user_info')

  if (!userInfoStr) {
    return null
  }

  try {
    return JSON.parse(userInfoStr)
  } catch (error) {
    console.error('Failed to parse user info:', error)
    return null
  }
}
