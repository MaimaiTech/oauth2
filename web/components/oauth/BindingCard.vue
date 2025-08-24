<script setup lang="ts">
import { computed, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Loading,
  Refresh,
  User,
} from '@element-plus/icons-vue'

import type { UserOAuthBinding,
} from '../../api/userOAuthApi'
import {
  supportsRefreshToken as checkSupportsRefreshToken,
  getProviderConfig,
  refreshToken,
  unbindAccount,
} from '../../api/userOAuthApi'

import StatusIndicator from './StatusIndicator.vue'

// Define props
interface Props {
  /** OAuth binding data */
  binding: UserOAuthBinding
  /** Loading state */
  loading?: boolean
  /** Error message */
  errorMessage?: string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const emit = defineEmits<Emits>()

// Define emits
interface Emits {
  (e: 'refresh', binding: UserOAuthBinding): void
  (e: 'unbind', binding: UserOAuthBinding): void
  (e: 'updated', binding: UserOAuthBinding): void
}

// Local state
const refreshing = ref(false)
const unbinding = ref(false)

// Provider configuration
const providerConfig = computed(() => getProviderConfig(props.binding.provider))

// Provider icon mapping
const iconComponents = {
  dingtalk: 'IconDingTalk',
  github: 'IconGitHub',
  gitee: 'IconGitee',
  feishu: 'IconFeishu',
  wechat: 'IconWechat',
  qq: 'IconQQ',
}

const providerIcon = computed(() => {
  return iconComponents[props.binding.provider] || 'IconOAuth'
})

// Token expiration checks
const isExpired = computed(() => {
  if (!props.binding.expires_at) { return false }
  return new Date(props.binding.expires_at) < new Date()
})

const isExpiringSoon = computed(() => {
  if (!props.binding.expires_at || isExpired.value) { return false }
  const expiryDate = new Date(props.binding.expires_at)
  const now = new Date()
  const diffDays = Math.ceil((expiryDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  return diffDays <= 7 // Expiring within 7 days
})

// Binding status computation
const bindingStatus = computed(() => {
  if (props.binding.status === 'disabled') { return 'disabled' }
  if (isExpired.value || props.binding.token_expired) { return 'expired' }
  if (props.errorMessage) { return 'error' }
  if (props.loading) { return 'pending' }
  if (props.binding.status === 'normal') { return 'connected' }
  return 'disconnected'
})

// Action availability
const supportsRefreshToken = computed(() => {
  return checkSupportsRefreshToken(props.binding.provider)
})

const canRefresh = computed(() => {
  return props.binding.status === 'normal'
    && !props.loading
    && (isExpired.value || isExpiringSoon.value)
})

// Card styling
const cardClass = computed(() => {
  return [
    `binding-card--${props.binding.provider}`,
    {
      'binding-card--expired': isExpired.value,
      'binding-card--expiring': isExpiringSoon.value,
      'binding-card--disabled': props.binding.status === 'disabled',
      'binding-card--loading': props.loading,
    },
  ]
})

// Date formatting
function formatDate(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return `今天 ${date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })}`
  }
  else if (diffDays === 1) {
    return `昨天 ${date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })}`
  }
  else if (diffDays < 7) {
    return `${diffDays}天前`
  }
  else {
    return `${date.toLocaleDateString('zh-CN')} ${date.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })}`
  }
}

// Handle refresh token
async function handleRefreshToken() {
  try {
    refreshing.value = true
    const result = await refreshToken(props.binding.provider)

    if (result.success) {
      ElMessage.success('令牌刷新成功')
      emit('refresh', props.binding)
      emit('updated', props.binding)
    }
    else {
      ElMessage.error(result.message || '令牌刷新失败')
    }
  }
  catch (error: any) {
    ElMessage.error(error.message || '令牌刷新失败')
  }
  finally {
    refreshing.value = false
  }
}

// Handle unbind account
async function handleUnbind() {
  try {
    await ElMessageBox.confirm(
      `确定要解绑 ${providerConfig.value.label} 账号吗？解绑后将无法使用该账号快速登录。`,
      '解绑确认',
      {
        confirmButtonText: '确定解绑',
        cancelButtonText: '取消',
        type: 'warning',
        confirmButtonClass: 'el-button--danger',
      },
    )

    unbinding.value = true
    const result = await unbindAccount(props.binding.provider)

    if (result.success) {
      ElMessage.success('账号解绑成功')
      emit('unbind', props.binding)
    }
    else {
      ElMessage.error(result.message || '账号解绑失败')
    }
  }
  catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(error.message || '账号解绑失败')
    }
  }
  finally {
    unbinding.value = false
  }
}
</script>

<template>
  <el-card class="oauth-binding-card" :class="cardClass" shadow="hover">
    <!-- Card Header -->
    <div class="binding-header">
      <div class="provider-info">
        <div class="provider-avatar" :style="{ backgroundColor: providerConfig.brand_color }">
          <el-icon :size="24">
            <component :is="providerIcon" />
          </el-icon>
        </div>
        <div class="provider-details">
          <h4 class="provider-name">
            {{ providerConfig.label }}
          </h4>
          <p class="provider-description">
            {{ binding.provider_username || binding.provider_nickname }}
          </p>
        </div>
      </div>
      <StatusIndicator
        :status="bindingStatus"
        :provider="binding.provider"
        :last-sync="binding.last_login_at"
        :error-message="errorMessage"
        :show-text="false"
        :icon-size="20"
      />
    </div>

    <!-- User Information -->
    <div class="binding-content">
      <div class="user-info">
        <el-avatar
          v-if="binding.provider_avatar"
          :src="binding.provider_avatar"
          :size="32"
          :alt="binding.provider_nickname || binding.provider_username"
        >
          <el-icon><User /></el-icon>
        </el-avatar>
        <el-avatar v-else :size="32">
          <el-icon><User /></el-icon>
        </el-avatar>

        <div class="user-details">
          <div class="user-name">
            {{ binding.provider_nickname || binding.provider_username }}
          </div>
          <div v-if="binding.provider_email" class="user-email">
            {{ binding.provider_email }}
          </div>
          <div class="user-id">
            ID: {{ binding.provider_user_id }}
          </div>
        </div>
      </div>

      <!-- Binding Status and Info -->
      <div class="binding-info">
        <div class="info-row">
          <span class="info-label">绑定时间</span>
          <span class="info-value">{{ formatDate(binding.created_at) }}</span>
        </div>
        <div v-if="binding.last_login_at" class="info-row">
          <span class="info-label">最后登录</span>
          <span class="info-value">{{ formatDate(binding.last_login_at) }}</span>
        </div>
        <div v-if="binding.expires_at" class="info-row">
          <span class="info-label">令牌过期</span>
          <span class="info-value" :class="{ 'text-warning': isExpiringSoon, 'text-danger': isExpired }">
            {{ formatDate(binding.expires_at) }}
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">连接状态</span>
          <StatusIndicator
            :status="bindingStatus"
            :provider="binding.provider"
            :show-tooltip="false"
            :icon-size="14"
          />
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="binding-actions">
      <el-button
        v-if="canRefresh && supportsRefreshToken"
        type="primary"
        size="small"
        plain
        :loading="refreshing"
        @click="handleRefreshToken"
      >
        <template #icon>
          <el-icon><Refresh /></el-icon>
        </template>
        刷新令牌
      </el-button>

      <el-button
        v-if="binding.can_unbind"
        type="danger"
        size="small"
        plain
        :loading="unbinding"
        @click="handleUnbind"
      >
        <template #icon>
          <el-icon />
        </template>
        解绑账号
      </el-button>

      <el-button
        v-if="!binding.can_unbind"
        type="info"
        size="small"
        disabled
      >
        不可解绑
      </el-button>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <el-icon class="is-loading" :size="24">
        <Loading />
      </el-icon>
    </div>
  </el-card>
</template>

<style lang="scss" scoped>
// Clean and Bright Variables (same as parent component)
:root {
  --primary-blue: #3b82f6;
  --success-green: #10b981;
  --warning-orange: #f59e0b;
  --danger-red: #ef4444;
  --white: #ffffff;
  --light-gray: #f8fafc;
  --lighter-gray: #f1f5f9;
  --text-dark: #1f2937;
  --text-gray: #6b7280;
  --border-light: #e5e7eb;
  --border-lighter: #f3f4f6;

  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
  --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);

  --radius-md: 8px;
  --radius-lg: 12px;

  --transition-fast: 0.15s ease;
  --transition-normal: 0.25s ease;
}

.oauth-binding-card {
  position: relative;
  background: var(--white);
  border: 1px solid var(--border-lighter);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: var(--transition-normal);
  box-shadow: var(--shadow-sm);

  &:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--border-light);
  }

  &.binding-card--loading {
    pointer-events: none;
    opacity: 0.7;
  }

  &.binding-card--expired {
    border-color: var(--warning-orange);
    background: linear-gradient(to right, rgba(245, 158, 11, 0.05), var(--white));
  }

  &.binding-card--expiring {
    border-color: var(--warning-orange);
    background: linear-gradient(to right, rgba(245, 158, 11, 0.03), var(--white));
  }

  &.binding-card--disabled {
    opacity: 0.6;
    background: var(--lighter-gray);
  }
}

.binding-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding: 20px 20px 0;

  .provider-info {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .provider-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    flex-shrink: 0;
  }

  .provider-details {
    .provider-name {
      margin: 0 0 4px 0;
      font-size: 16px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .provider-description {
      margin: 0;
      font-size: 14px;
      color: var(--text-gray);
    }
  }
}

.binding-content {
  padding: 0 20px;
  margin-bottom: 20px;

  .user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 16px;
    background: var(--lighter-gray);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-lighter);

    .user-details {
      flex: 1;

      .user-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 4px;
      }

      .user-email {
        font-size: 13px;
        color: var(--text-gray);
        margin-bottom: 2px;
      }

      .user-id {
        font-size: 11px;
        color: var(--text-gray);
        font-family: 'Monaco', 'Menlo', monospace;
        opacity: 0.7;
      }
    }
  }

  .binding-info {
    .info-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 13px;

      &:last-child {
        margin-bottom: 0;
      }

      .info-label {
        color: var(--text-gray);
        font-weight: 500;
      }

      .info-value {
        color: var(--text-dark);
        font-weight: 500;

        &.text-warning {
          color: var(--warning-orange);
        }

        &.text-danger {
          color: var(--danger-red);
        }
      }
    }
  }
}

.binding-actions {
  padding: 0 20px 20px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;

  :deep(.el-button) {
    flex: 1;
    min-width: 100px;
    border-radius: var(--radius-md);
    font-weight: 500;
    transition: var(--transition-fast);

    &.el-button--primary {
      background: var(--primary-blue);
      border-color: var(--primary-blue);

      &:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
      }
    }

    &.el-button--danger {
      background: var(--danger-red);
      border-color: var(--danger-red);

      &:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
      }
    }
  }
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-lg);
  backdrop-filter: blur(2px);

  .el-icon {
    color: var(--primary-blue);
  }
}

// Mobile responsiveness
@media (max-width: 768px) {
  .binding-header {
    padding: 16px 16px 0;

    .provider-avatar {
      width: 36px;
      height: 36px;
    }

    .provider-details {
      .provider-name {
        font-size: 15px;
      }

      .provider-description {
        font-size: 13px;
      }
    }
  }

  .binding-content {
    padding: 0 16px;

    .user-info {
      padding: 12px;
    }
  }

  .binding-actions {
    padding: 0 16px 16px;

    :deep(.el-button) {
      font-size: 13px;
      min-width: 80px;
    }
  }
}

// Clean Provider-specific colors
.binding-card--dingtalk .provider-avatar {
  background: #0089ff;
}

.binding-card--github .provider-avatar {
  background: #24292e;
}

.binding-card--gitee .provider-avatar {
  background: #c71c27;
}

.binding-card--feishu .provider-avatar {
  background: #00d4aa;
}

.binding-card--wechat .provider-avatar {
  background: #07c160;
}

.binding-card--qq .provider-avatar {
  background: #12b7f5;
}
</style>
