<script setup lang="ts">
import { computed } from 'vue'
import type { OAuthProviderName } from '../../api/types'
import { getProviderName } from '../../api/userOAuthApi'

// Define props
interface Props {
  /** Connection status */
  status: 'connected' | 'disconnected' | 'expired' | 'error' | 'pending' | 'disabled'
  /** OAuth provider name */
  provider?: OAuthProviderName
  /** Last sync timestamp */
  lastSync?: string
  /** Error message if status is error */
  errorMessage?: string
  /** Show status text alongside icon */
  showText?: boolean
  /** Icon size */
  iconSize?: number
  /** Enable tooltip */
  showTooltip?: boolean
  /** Additional status info */
  additionalInfo?: string
}

const props = withDefaults(defineProps<Props>(), {
  showText: true,
  iconSize: 16,
  showTooltip: true,
})

// Status configuration mapping
const statusConfig = {
  connected: {
    icon: 'CircleCheckFilled',
    text: '已连接',
    class: 'status-connected',
    color: '#67c23a',
  },
  disconnected: {
    icon: 'CircleCloseFilled',
    text: '未连接',
    class: 'status-disconnected',
    color: '#909399',
  },
  expired: {
    icon: 'WarningFilled',
    text: '已过期',
    class: 'status-expired',
    color: '#e6a23c',
  },
  error: {
    icon: 'CircleCloseFilled',
    text: '错误',
    class: 'status-error',
    color: '#f56c6c',
  },
  pending: {
    icon: 'Clock',
    text: '处理中',
    class: 'status-pending',
    color: '#409eff',
  },
  disabled: {
    icon: 'CircleCloseFilled',
    text: '已禁用',
    class: 'status-disabled',
    color: '#c0c4cc',
  },
}

// Computed status configuration
const currentStatusConfig = computed(() => statusConfig[props.status])

// Status icon component
const statusIcon = computed(() => currentStatusConfig.value.icon)

// Status text
const statusText = computed(() => currentStatusConfig.value.text)

// Status CSS class
const statusClass = computed(() => [
  'oauth-status-indicator',
  currentStatusConfig.value.class,
  {
    'oauth-status-indicator--with-text': props.showText,
    'oauth-status-indicator--icon-only': !props.showText,
  },
])

// Tooltip content
const tooltipContent = computed(() => {
  let content = ''

  if (props.provider) {
    content += `${getProviderName(props.provider)}: `
  }

  content += currentStatusConfig.value.text

  if (props.status === 'error' && props.errorMessage) {
    content += `\n错误: ${props.errorMessage}`
  }

  if (props.status === 'connected' && props.lastSync) {
    content += `\n最后同步: ${formatLastSync(props.lastSync)}`
  }

  if (props.status === 'expired') {
    content += '\n请重新授权连接'
  }

  if (props.additionalInfo) {
    content += `\n${props.additionalInfo}`
  }

  return content
})

// Format last sync time
function formatLastSync(timestamp: string): string {
  const date = new Date(timestamp)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMinutes = Math.floor(diffMs / (1000 * 60))
  const diffHours = Math.floor(diffMinutes / 60)
  const diffDays = Math.floor(diffHours / 24)

  if (diffMinutes < 1) {
    return '刚刚'
  }
  else if (diffMinutes < 60) {
    return `${diffMinutes}分钟前`
  }
  else if (diffHours < 24) {
    return `${diffHours}小时前`
  }
  else if (diffDays < 7) {
    return `${diffDays}天前`
  }
  else {
    return date.toLocaleDateString('zh-CN')
  }
}
</script>

<template>
  <div class="oauth-status-indicator" :class="statusClass">
    <el-tooltip
      :content="tooltipContent"
      placement="top"
      :disabled="!showTooltip"
    >
      <div class="status-content">
        <el-icon class="status-icon" :size="iconSize">
          <component :is="statusIcon" />
        </el-icon>
        <span v-if="showText" class="status-text">{{ statusText }}</span>
      </div>
    </el-tooltip>
  </div>
</template>

<style lang="scss" scoped>
.oauth-status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  cursor: default;

  &--icon-only {
    .status-text {
      display: none;
    }
  }

  &--with-text {
    .status-content {
      display: flex;
      align-items: center;
      gap: 6px;
    }
  }

  .status-content {
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease-in-out;
  }

  .status-icon {
    flex-shrink: 0;
    transition: all 0.2s ease-in-out;
  }

  .status-text {
    font-size: 14px;
    font-weight: 500;
    line-height: 1;
    white-space: nowrap;
  }

  // Status-specific styles
  &.status-connected {
    .status-icon {
      color: #67c23a;
    }
    .status-text {
      color: #67c23a;
    }

    &:hover .status-content {
      transform: scale(1.05);
    }
  }

  &.status-disconnected {
    .status-icon {
      color: #909399;
    }
    .status-text {
      color: #909399;
    }
  }

  &.status-expired {
    .status-icon {
      color: #e6a23c;
      animation: pulse-warning 2s ease-in-out infinite;
    }
    .status-text {
      color: #e6a23c;
    }
  }

  &.status-error {
    .status-icon {
      color: #f56c6c;
      animation: shake 0.5s ease-in-out;
    }
    .status-text {
      color: #f56c6c;
    }
  }

  &.status-pending {
    .status-icon {
      color: #409eff;
      animation: spin 1s linear infinite;
    }
    .status-text {
      color: #409eff;
    }
  }

  &.status-disabled {
    .status-icon {
      color: #c0c4cc;
    }
    .status-text {
      color: #c0c4cc;
    }

    opacity: 0.7;
  }
}

// Animations
@keyframes pulse-warning {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(1.1);
  }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
  20%, 40%, 60%, 80% { transform: translateX(2px); }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

// Size variants
.oauth-status-indicator {
  &--small {
    font-size: 12px;
    gap: 4px;

    .status-icon {
      font-size: 12px;
    }
  }

  &--large {
    font-size: 16px;
    gap: 8px;

    .status-icon {
      font-size: 20px;
    }
  }
}

// Tooltip improvements
:deep(.el-tooltip__trigger) {
  display: flex;
  align-items: center;
}

// Dark mode support
@media (prefers-color-scheme: dark) {
  .oauth-status-indicator {
    &.status-disconnected {
      .status-icon,
      .status-text {
        color: #73767a;
      }
    }

    &.status-disabled {
      .status-icon,
      .status-text {
        color: #6c6e72;
      }
    }
  }
}

// Mobile responsiveness
@media (max-width: 768px) {
  .oauth-status-indicator {
    font-size: 13px;
    gap: 4px;

    .status-icon {
      font-size: 14px;
    }
  }
}
</style>
