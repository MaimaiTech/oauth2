<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElButton, ElIcon } from 'element-plus'
import { Loading, Lock } from '@element-plus/icons-vue'
import ProviderIcon from './ProviderIcon.vue'
import {
  getLoginProviders,
  getProviderDisplayConfig,
  generateLoginUrl,
  initiateOAuthLogin
} from '$/maimaitech/oauth2/api/loginApi'
import type { OAuthProvider, OAuthProviderName } from '$/maimaitech/oauth2/api/types'
import provider from "@/layouts/provider.tsx";


// Props definition
interface Props {
  /** 显示模式：horizontal（水平）或 vertical（垂直） */
  layout?: 'horizontal' | 'vertical'
  /** 按钮大小 */
  size?: 'large' | 'default' | 'small'
  /** 是否显示为圆形按钮 */
  circle?: boolean
  /** 是否只显示图标 */
  iconOnly?: boolean
  /** 登录成功后的重定向URL */
  redirectUri: (provider: OAuthProviderName) => string
  /** 最大显示的提供商数量 */
  maxProviders?: number
  /** 自定义样式类名 */
  customClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  layout: 'horizontal',
  size: 'default',
  circle: false,
  iconOnly: false,
  maxProviders: 6,
  customClass: ''
})

// Emits
const emit = defineEmits<{
  'provider-click': [provider: OAuthProviderName]
  'loading-change': [loading: boolean]
  'error': [error: string]
}>()

// Reactive state
const providers = ref<OAuthProvider[]>([])
const loading = ref(false)
const loadingProvider = ref<OAuthProviderName | null>(null)

const containerClasses = computed(() => [
  'oauth-login-buttons',
  `oauth-login-buttons--${props.layout}`,
  `oauth-login-buttons--${props.size}`,
  {
    'oauth-login-buttons--circle': props.circle,
    'oauth-login-buttons--icon-only': props.iconOnly
  },
  props.customClass
])

// 获取提供商列表
const fetchProviders = async () => {
  try {
    loading.value = true
    emit('loading-change', true)

    // 使用 MineAdmin 的 useHttp 方法调用 API
    const response = await getLoginProviders()
    if (response.code === 200) {
      providers.value = response.data || []
    } else {
      throw new Error(response.message || '获取OAuth提供商失败')
    }
  } catch (error) {
    console.error('获取OAuth提供商失败:', error)
    const errorMsg = error instanceof Error ? error.message : '获取OAuth提供商失败'
    ElMessage.error(errorMsg)
    emit('error', errorMsg)
  } finally {
    loading.value = false
    emit('loading-change', false)
  }
}

// 处理提供商点击
const handleProviderClick = async (provider: OAuthProviderName) => {
  if (loadingProvider.value) return

  try {
    loadingProvider.value = provider
    emit('provider-click', provider)

    // 使用 API 生成登录 URL
    const result = await initiateOAuthLogin(provider, props.redirectUri(provider))
    const loginUrl = result.data.auth_url
    // console.log(loginUrl)
    // 跳转到授权页面
    window.location.href = loginUrl

  } catch (error) {
    console.error(`${provider} 登录失败:`, error)
    const errorMsg = error instanceof Error ? error.message : `${provider} 登录失败`
    ElMessage.error(errorMsg)
    emit('error', errorMsg)
    loadingProvider.value = null
  }
}

// 获取提供商配置
const getProviderConfig = (name: OAuthProviderName) => {
  return getProviderDisplayConfig(name)
}

// 获取按钮样式
const getButtonStyle = (provider: OAuthProviderName) => {
  const config = getProviderConfig(provider)
  return {
    '--oauth-brand-color': config.brandColor,
    '--oauth-text-color': config.textColor,
    '--oauth-hover-color': adjustColor(config.brandColor, -20),
    '--oauth-active-color': adjustColor(config.brandColor, -40)
  }
}

// 颜色调整辅助函数
const adjustColor = (color: string, amount: number): string => {
  const num = parseInt(color.replace('#', ''), 16)
  const amt = Math.round(2.55 * amount)
  const R = (num >> 16) + amt
  const G = (num >> 8 & 0x00FF) + amt
  const B = (num & 0x0000FF) + amt
  return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
    (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
    (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1)
}

// 生命周期
onMounted(() => {
  fetchProviders()
})

// 暴露方法供父组件调用
defineExpose({
  refresh: fetchProviders,
  providers: providers
})
</script>

<template>
  <div :class="containerClasses">
    <!-- 加载状态 -->
    <div v-if="loading" class="oauth-loading">
      <el-icon class="is-loading">
        <Loading />
      </el-icon>
      <span class="oauth-loading-text">加载登录方式...</span>
    </div>

    <!-- 无可用提供商 -->
    <div v-else-if="providers.length === 0" class="oauth-empty">
      <el-icon><Lock /></el-icon>
      <span class="oauth-empty-text">暂无可用的登录方式</span>
    </div>

    <!-- OAuth 提供商按钮列表 -->
    <div v-else class="oauth-providers">
      <el-button
        v-for="provider in providers"
        :key="provider.id"
        :size="size"
        :circle="circle"
        :loading="loadingProvider === provider.name"
        :style="getButtonStyle(provider.name)"
        class="oauth-provider-button"
        @click="handleProviderClick(provider.name)"
      >
        <!-- 图标 -->
        <template #icon>
          <ProviderIcon
            :provider="provider.name"
            class="oauth-provider-icon"
            :size="size === 'large' ? 20 : size === 'small' ? 14 : 16"
          />
        </template>

        <!-- 文本内容 -->
        <span v-if="!iconOnly" class="oauth-provider-text">
          {{ circle ? '' : `使用${getProviderConfig(provider.name).name}登录` }}
        </span>
      </el-button>
    </div>

    <!-- 提示信息 -->
    <div v-if="providers.length > 0 && !iconOnly" class="oauth-tips">
      <span class="oauth-tips-text">选择第三方账号快速登录</span>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.oauth-login-buttons {
  --oauth-gap: 12px;
  --oauth-border-radius: 6px;
  --oauth-transition: all 0.3s ease;

  display: flex;
  flex-direction: column;
  gap: var(--oauth-gap);

  // 水平布局
  &--horizontal {
    .oauth-providers {
      display: flex;
      flex-wrap: wrap;
      gap: var(--oauth-gap);
      justify-content: center;
      align-items: center;
    }
  }

  // 垂直布局
  &--vertical {
    .oauth-providers {
      display: flex;
      flex-direction: column;
      gap: var(--oauth-gap);
      align-items: stretch;
    }
  }

  // 不同尺寸
  &--large {
    --oauth-gap: 16px;
    --oauth-border-radius: 8px;
  }

  &--small {
    --oauth-gap: 8px;
    --oauth-border-radius: 4px;
  }

  // 圆形按钮模式
  &--circle {
    .oauth-providers {
      justify-content: center;

      .oauth-provider-button {
        flex: none;
      }
    }
  }

  // 仅图标模式
  &--icon-only {
    .oauth-providers {
      justify-content: center;
    }
  }
}

// 加载状态
.oauth-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px;
  color: var(--el-text-color-regular);

  .oauth-loading-text {
    font-size: 14px;
  }
}

// 空状态
.oauth-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px;
  color: var(--el-text-color-placeholder);

  .oauth-empty-text {
    font-size: 14px;
  }
}

// 提供商按钮
.oauth-provider-button {
  position: relative;
  transition: var(--oauth-transition);
  border: 1px solid var(--oauth-brand-color);
  background-color: var(--oauth-brand-color);
  color: var(--oauth-text-color);
  border-radius: var(--oauth-border-radius);

  &:hover:not(:disabled) {
    background-color: var(--oauth-hover-color);
    border-color: var(--oauth-hover-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  &:active:not(:disabled) {
    background-color: var(--oauth-active-color);
    border-color: var(--oauth-active-color);
    transform: translateY(0);
  }

  &:focus-visible {
    outline: 2px solid var(--oauth-brand-color);
    outline-offset: 2px;
  }

  // 垂直布局下的按钮
  .oauth-login-buttons--vertical & {
    justify-content: flex-start;
    padding-left: 16px;
    min-height: 40px;

    .oauth-provider-icon {
      margin-right: 8px;
    }
  }

  // 圆形按钮
  .oauth-login-buttons--circle & {
    padding: 0;
    aspect-ratio: 1;
  }
}

// 提示信息
.oauth-tips {
  text-align: center;
  margin-top: 8px;

  .oauth-tips-text {
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }
}

// 响应式设计
@media (max-width: 768px) {
  .oauth-login-buttons--horizontal {
    .oauth-providers {
      flex-direction: column;
      align-items: stretch;
    }
  }

  .oauth-provider-button {
    min-height: 44px; // 提高移动端点击区域
  }
}

// 暗色模式适配
@media (prefers-color-scheme: dark) {
  .oauth-provider-button {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);

    &:hover:not(:disabled) {
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
    }
  }
}

// 高对比度模式
@media (prefers-contrast: high) {
  .oauth-provider-button {
    border-width: 2px;

    &:focus-visible {
      outline-width: 3px;
    }
  }
}

// 减少动画效果（用户偏好）
@media (prefers-reduced-motion: reduce) {
  .oauth-provider-button {
    transition: none;

    &:hover:not(:disabled) {
      transform: none;
    }
  }
}
</style>
