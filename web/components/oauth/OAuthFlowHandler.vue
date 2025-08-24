<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  CircleCheckFilled,
  CircleCloseFilled,
  Loading,
} from '@element-plus/icons-vue'

import type { OAuthProviderName } from '../../api/types'
import {
  clearOAuthState,
  getProviderConfig,
  handleOAuthCallback,
  retrieveOAuthState,
  validateOAuthState,
} from '../../api/userOAuthApi'

// Define props
interface Props {
  /** Auto start processing on mount */
  autoStart?: boolean
  /** Redirect URL after success */
  successRedirect?: string
  /** Redirect URL after error/cancel */
  errorRedirect?: string
}

const props = withDefaults(defineProps<Props>(), {
  autoStart: true,
  successRedirect: '/personal/bindings',
  errorRedirect: '/personal/bindings',
})

const emit = defineEmits<Emits>()

// Define emits
interface Emits {
  (e: 'success', result: any): void
  (e: 'error', error: string): void
  (e: 'complete'): void
}

// Router and route
const route = useRoute()
const router = useRouter()

// Component state
const isProcessing = ref(false)
const isSuccess = ref(false)
const isError = ref(false)
const currentStep = ref(0)
const processingMessage = ref('正在验证授权信息...')
const successMessage = ref('')
const errorMessage = ref('')
const errorDetails = ref('')
const bindingResult = ref<any>(null)

// OAuth parameters from URL
const provider = computed(() => route.params.provider as OAuthProviderName)
const code = computed(() => route.query.code as string)
const state = computed(() => route.query.state as string)
const error = computed(() => route.query.error as string)
const errorDescription = computed(() => route.query.error_description as string)

// Provider information
const providerConfig = computed(() => {
  if (!provider.value) { return null }
  return getProviderConfig(provider.value)
})

const providerName = computed(() => providerConfig.value?.label || '')
const providerColor = computed(() => providerConfig.value?.brand_color || '#666')
const providerIcon = computed(() => {
  const iconMap: Record<string, string> = {
    dingtalk: 'IconDingTalk',
    github: 'IconGitHub',
    gitee: 'IconGitee',
    feishu: 'IconFeishu',
    wechat: 'IconWechat',
    qq: 'IconQQ',
  }
  return iconMap[provider.value] || 'IconOAuth'
})

// Processing timeout
let processingTimeout: NodeJS.Timeout | null = null

// Mount hook
onMounted(() => {
  if (props.autoStart) {
    startProcessing()
  }
})

// Cleanup
onUnmounted(() => {
  if (processingTimeout) {
    clearTimeout(processingTimeout)
  }
})

// Start OAuth processing
async function startProcessing() {
  // Check for OAuth error first
  if (error.value) {
    handleOAuthError()
    return
  }

  // Validate required parameters
  if (!provider.value || !code.value || !state.value) {
    showError('OAuth 参数无效', '缺少必要的授权参数')
    return
  }

  // Validate state parameter
  const storedState = retrieveOAuthState(provider.value)
  if (!storedState || !validateOAuthState(state.value, storedState)) {
    showError('OAuth 安全验证失败', 'State 参数验证失败，可能存在安全风险')
    return
  }

  try {
    isProcessing.value = true
    currentStep.value = 1
    processingMessage.value = '正在验证授权码...'

    // Set processing timeout (30 seconds)
    processingTimeout = setTimeout(() => {
      if (isProcessing.value) {
        showError('处理超时', 'OAuth 处理时间过长，请重试')
      }
    }, 30000)

    // Step 2: Get user information
    currentStep.value = 2
    processingMessage.value = '正在获取用户信息...'
    await new Promise(resolve => setTimeout(resolve, 500))

    // Step 3: Bind account
    currentStep.value = 3
    processingMessage.value = '正在绑定账号...'

    const result = await handleOAuthCallback(provider.value, code.value, state.value)

    if (processingTimeout) {
      clearTimeout(processingTimeout)
      processingTimeout = null
    }

    if (result.data.success) {
      showSuccess(result.data)
    }
    else {
      showError('绑定失败', result.data.message)
    }
  }
  catch (error: any) {
    if (processingTimeout) {
      clearTimeout(processingTimeout)
      processingTimeout = null
    }

    showError(
      '处理失败',
      error.response?.data?.message || error.message || '未知错误',
    )
  }
  finally {
    // Clear OAuth state
    clearOAuthState(provider.value)
  }
}

// Handle OAuth error from provider
function handleOAuthError() {
  const errorMessages: Record<string, string> = {
    access_denied: '用户取消了授权',
    invalid_request: '无效的授权请求',
    unsupported_response_type: '不支持的响应类型',
    invalid_scope: '无效的授权范围',
    server_error: '服务器错误',
    temporarily_unavailable: '服务暂时不可用',
  }

  const message = errorMessages[error.value] || '授权失败'
  const details = errorDescription.value || `错误代码: ${error.value}`

  showError(message, details)
}

// Show success state
function showSuccess(result: any) {
  isProcessing.value = false
  isSuccess.value = true
  bindingResult.value = result.binding
  successMessage.value = result.message || `${providerName.value} 账号绑定成功`

  emit('success', result)
  ElMessage.success(successMessage.value)
}

// Show error state
function showError(title: string, details?: string) {
  isProcessing.value = false
  isError.value = true
  errorMessage.value = title
  errorDetails.value = details || ''

  emit('error', title)
  ElMessage.error(title)
}

// Handle continue button
function handleContinue() {
  emit('complete')
  if (props.successRedirect) {
    router.push(props.successRedirect)
  }
}

// Handle close button
function handleClose() {
  emit('complete')
  if (isError.value && props.errorRedirect) {
    router.push(props.errorRedirect)
  }
  else if (props.successRedirect) {
    router.push(props.successRedirect)
  }
}

// Handle retry button
function handleRetry() {
  isError.value = false
  errorMessage.value = ''
  errorDetails.value = ''
  currentStep.value = 0
  startProcessing()
}
</script>

<template>
  <div class="oauth-flow-handler">
    <!-- Processing State -->
    <div v-if="isProcessing" class="processing-state">
      <div class="processing-content">
        <el-icon class="processing-icon is-loading" :size="48">
          <Loading />
        </el-icon>
        <h3 class="processing-title">
          正在处理 OAuth 授权...
        </h3>
        <p class="processing-message">
          {{ processingMessage }}
        </p>
        <div class="processing-steps">
          <div class="step" :class="{ active: currentStep >= 1 }">
            <el-icon><CircleCheckFilled /></el-icon>
            <span>验证授权码</span>
          </div>
          <div class="step" :class="{ active: currentStep >= 2 }">
            <el-icon><CircleCheckFilled /></el-icon>
            <span>获取用户信息</span>
          </div>
          <div class="step" :class="{ active: currentStep >= 3 }">
            <el-icon><CircleCheckFilled /></el-icon>
            <span>绑定账号</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Success State -->
    <div v-else-if="isSuccess" class="success-state">
      <div class="success-content">
        <el-icon class="success-icon" :size="64" color="#67c23a">
          <CircleCheckFilled />
        </el-icon>
        <h3 class="success-title">
          OAuth 绑定成功！
        </h3>
        <p class="success-message">
          {{ successMessage }}
        </p>
        <div v-if="bindingResult" class="binding-info">
          <el-card class="binding-result-card">
            <div class="provider-info">
              <div class="provider-avatar" :style="{ backgroundColor: providerColor }">
                <el-icon :size="24">
                  <component :is="providerIcon" />
                </el-icon>
              </div>
              <div class="provider-details">
                <h4>{{ providerName }}</h4>
                <p>{{ bindingResult.provider_username }}</p>
              </div>
            </div>
          </el-card>
        </div>
        <div class="success-actions">
          <el-button type="primary" @click="handleContinue">
            继续
          </el-button>
          <el-button @click="handleClose">
            关闭
          </el-button>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="isError" class="error-state">
      <div class="error-content">
        <el-icon class="error-icon" :size="64" color="#f56c6c">
          <CircleCloseFilled />
        </el-icon>
        <h3 class="error-title">
          OAuth 绑定失败
        </h3>
        <p class="error-message">
          {{ errorMessage }}
        </p>
        <div v-if="errorDetails" class="error-details">
          <el-collapse>
            <el-collapse-item title="错误详情" name="details">
              <pre class="error-stack">{{ errorDetails }}</pre>
            </el-collapse-item>
          </el-collapse>
        </div>
        <div class="error-actions">
          <el-button type="primary" @click="handleRetry">
            重试
          </el-button>
          <el-button @click="handleClose">
            关闭
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.oauth-flow-handler {
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

// Processing state
.processing-state {
  text-align: center;
  max-width: 400px;

  .processing-content {
    .processing-icon {
      margin-bottom: 20px;
      color: var(--el-color-primary);
    }

    .processing-title {
      margin: 0 0 12px 0;
      font-size: 24px;
      color: var(--el-text-color-primary);
      font-weight: 600;
    }

    .processing-message {
      margin: 0 0 30px 0;
      color: var(--el-text-color-regular);
      font-size: 16px;
    }

    .processing-steps {
      display: flex;
      flex-direction: column;
      gap: 12px;

      .step {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        border-radius: 8px;
        background-color: var(--el-fill-color-light);
        transition: all 0.3s ease-in-out;
        opacity: 0.5;

        &.active {
          opacity: 1;
          background-color: var(--el-color-primary-light-9);
          color: var(--el-color-primary);

          .el-icon {
            color: var(--el-color-primary);
          }
        }

        .el-icon {
          font-size: 16px;
          color: var(--el-text-color-placeholder);
        }

        span {
          font-size: 14px;
          font-weight: 500;
        }
      }
    }
  }
}

// Success state
.success-state {
  text-align: center;
  max-width: 500px;

  .success-content {
    .success-icon {
      margin-bottom: 20px;
    }

    .success-title {
      margin: 0 0 12px 0;
      font-size: 24px;
      color: var(--el-color-success);
      font-weight: 600;
    }

    .success-message {
      margin: 0 0 24px 0;
      color: var(--el-text-color-regular);
      font-size: 16px;
    }

    .binding-info {
      margin-bottom: 24px;

      .binding-result-card {
        border-radius: 12px;

        .provider-info {
          display: flex;
          align-items: center;
          gap: 16px;

          .provider-avatar {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
          }

          .provider-details {
            text-align: left;

            h4 {
              margin: 0 0 4px 0;
              font-size: 16px;
              font-weight: 600;
              color: var(--el-text-color-primary);
            }

            p {
              margin: 0;
              font-size: 14px;
              color: var(--el-text-color-regular);
            }
          }
        }
      }
    }

    .success-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
    }
  }
}

// Error state
.error-state {
  text-align: center;
  max-width: 500px;

  .error-content {
    .error-icon {
      margin-bottom: 20px;
    }

    .error-title {
      margin: 0 0 12px 0;
      font-size: 24px;
      color: var(--el-color-danger);
      font-weight: 600;
    }

    .error-message {
      margin: 0 0 20px 0;
      color: var(--el-text-color-regular);
      font-size: 16px;
    }

    .error-details {
      margin-bottom: 24px;
      text-align: left;

      .error-stack {
        background-color: var(--el-fill-color-light);
        border-radius: 6px;
        padding: 12px;
        font-size: 12px;
        color: var(--el-text-color-regular);
        font-family: 'Monaco', 'Consolas', monospace;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 200px;
        overflow-y: auto;
      }
    }

    .error-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
    }
  }
}

// Loading animation
@keyframes processing-pulse {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.05);
    opacity: 0.8;
  }
}

.processing-icon.is-loading {
  animation: processing-pulse 2s ease-in-out infinite;
}

// Dark mode support
@media (prefers-color-scheme: dark) {
  .error-details .error-stack {
    background-color: var(--el-fill-color-darker);
    color: var(--el-text-color-primary);
  }
}

// Mobile responsiveness
@media (max-width: 768px) {
  .oauth-flow-handler {
    padding: 16px;
    min-height: 350px;
  }

  .processing-state,
  .success-state,
  .error-state {
    max-width: 100%;
  }

  .processing-content .processing-title,
  .success-content .success-title,
  .error-content .error-title {
    font-size: 20px;
  }

  .processing-content .processing-message,
  .success-content .success-message,
  .error-content .error-message {
    font-size: 14px;
  }

  .processing-steps .step {
    padding: 8px 12px;
    font-size: 13px;
  }

  .success-actions,
  .error-actions {
    flex-direction: column;

    .el-button {
      width: 100%;
    }
  }
}
</style>
