/**
 * OAuth2 Plugin TypeScript Type Definitions
 * 
 * Defines interfaces and types for OAuth2 providers and user bindings
 * following MineAdmin patterns and backend API structure
 */

import type { ResponseStruct } from '#/global'

/**
 * OAuth Provider Types
 */
export type OAuthProviderName = 'dingtalk' | 'github' | 'gitee' | 'feishu' | 'wechat' | 'qq'

export type OAuthStatus = 'normal' | 'disabled' | 'pending'

/**
 * OAuth Provider Configuration Interface
 */
export interface OAuthProvider {
  /** 主键ID */
  id: number
  /** 服务商名称 */
  name: OAuthProviderName
  /** 显示名称 */
  display_name: string
  /** 应用ID/客户端ID */
  client_id: string
  /** 应用密钥/客户端密钥 (仅在编辑时显示) */
  client_secret?: string
  /** 回调地址 */
  redirect_uri: string
  /** OAuth授权范围 */
  scopes?: string[]
  /** 平台特定配置参数 */
  extra_config?: Record<string, any>
  /** 启用状态 */
  enabled: boolean
  /** 状态 */
  status: OAuthStatus
  /** 排序 */
  sort: number
  /** 创建者 */
  created_by: number
  /** 更新者 */
  updated_by: number
  /** 创建时间 */
  created_at: string
  /** 更新时间 */
  updated_at: string
  /** 备注 */
  remark?: string
  /** 统计数据 */
  stats?: {
    total_bindings: number
    active_bindings: number
    inactive_bindings: number
  }
}

/**
 * Create Provider Request
 */
export interface CreateProviderRequest {
  name: OAuthProviderName
  display_name: string
  client_id: string
  client_secret: string
  redirect_uri: string
  scopes?: string[]
  extra_config?: Record<string, any>
  enabled?: boolean
  sort?: number
  remark?: string
}

/**
 * Update Provider Request
 */
export interface UpdateProviderRequest {
  display_name?: string
  client_id?: string
  client_secret?: string
  redirect_uri?: string
  scopes?: string[]
  extra_config?: Record<string, any>
  enabled?: boolean
  sort?: number
  remark?: string
}

/**
 * Provider Query Parameters
 */
export interface ProviderQueryParams {
  page?: number
  page_size?: number
  name?: string
  enabled?: boolean
  status?: OAuthStatus
  keyword?: string
}

/**
 * Provider Toggle Request
 */
export interface ProviderToggleRequest {
  enabled: boolean
}

/**
 * Test Connection Result
 */
export interface TestConnectionResult {
  status: 'success' | 'failed'
  response_time?: number
  auth_url?: string
  error?: string
  message?: string
}

/**
 * User OAuth Account Interface
 */
export interface UserOAuthAccount {
  /** 主键ID */
  id: number
  /** 系统用户ID */
  user_id: number
  /** OAuth提供者名称 */
  provider: OAuthProviderName
  /** 第三方用户唯一标识 */
  provider_user_id: string
  /** 第三方用户名 */
  provider_username: string
  /** 第三方用户昵称 */
  provider_nickname?: string
  /** 第三方用户头像 */
  provider_avatar?: string
  /** 第三方用户邮箱 */
  provider_email?: string
  /** OAuth访问令牌 */
  access_token: string
  /** 令牌过期时间 */
  expires_at?: string
  /** 刷新令牌 */
  refresh_token?: string
  /** 第三方用户原始数据 */
  raw_data?: Record<string, any>
  /** 绑定状态 */
  status: OAuthStatus
  /** 最后登录时间 */
  last_login_at?: string
  /** 创建时间 */
  created_at: string
  /** 更新时间 */
  updated_at: string
  /** 用户信息 */
  user?: {
    id: number
    username: string
    nickname?: string
    email?: string
    avatar?: string
  }
  /** 提供者信息 */
  provider_info?: {
    display_name: string
    icon: string
    brand_color: string
  }
}

/**
 * User Bindings Query Parameters
 */
export interface UserBindingsQueryParams {
  page?: number
  page_size?: number
  provider?: OAuthProviderName
  user_id?: number
  username?: string
  provider_username?: string
  status?: OAuthStatus
  date_from?: string
  date_to?: string
}

/**
 * Force Unbind Result
 */
export interface ForceUnbindResult {
  user_id: number
  provider: OAuthProviderName
  unbound_at: string
}

/**
 * Binding Statistics
 */
export interface BindingStatistics {
  total_bindings: number
  active_providers: Record<OAuthProviderName, number>
  recent_bindings: number
  monthly_growth: number
  provider_distribution: Array<{
    provider: OAuthProviderName
    count: number
    percentage: number
    growth: number
  }>
  time_series: Array<{
    date: string
    bindings: number
    new_bindings: number
  }>
}

/**
 * Batch Operation Request
 */
export interface BatchOperationRequest {
  action: 'unbind' | 'disable' | 'enable'
  binding_ids: number[]
  reason?: string
}

/**
 * Batch Operation Result
 */
export interface BatchOperationResult {
  success: number
  failed: number
  details: Array<{
    id: number
    status: 'success' | 'failed'
    reason?: string
  }>
}

/**
 * Export Request
 */
export interface ExportRequest {
  format?: 'csv' | 'excel'
  provider?: OAuthProviderName
  date_from?: string
  date_to?: string
}

/**
 * Export Result
 */
export interface ExportResult {
  task_id: string
  download_url: string
  estimated_time: number
}

/**
 * Provider Option for Select Components
 */
export interface ProviderOption {
  value: OAuthProviderName
  label: string
  icon: string
  brand_color: string
  default_scopes: string[]
  supports_refresh_token: boolean
}

/**
 * API Response Types
 */
export type ProviderListResponse = ResponseStruct<{
  items: OAuthProvider[]
  total: number
  page: number
  page_size: number
}>

export type ProviderResponse = ResponseStruct<OAuthProvider>

export type UserBindingsListResponse = ResponseStruct<{
  items: UserOAuthAccount[]
  total: number
  page: number
  page_size: number
}>

export type UserBindingsResponse = ResponseStruct<UserOAuthAccount[]>

export type TestConnectionResponse = ResponseStruct<TestConnectionResult>

export type ForceUnbindResponse = ResponseStruct<ForceUnbindResult>

export type StatisticsResponse = ResponseStruct<BindingStatistics>

export type BatchOperationResponse = ResponseStruct<BatchOperationResult>

export type ExportResponse = ResponseStruct<ExportResult>

/**
 * OAuth Callback Response for generic callback handling
 */
export interface OAuthCallbackResult {
  success: boolean
  message: string
  provider?: OAuthProviderName
  binding?: UserOAuthAccount
  redirect_url?: string
  error_code?: string
}

export type OAuthCallbackResponse = ResponseStruct<OAuthCallbackResult>