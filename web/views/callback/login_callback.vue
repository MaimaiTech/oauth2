<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { CircleCloseFilled, Loading, SuccessFilled } from '@element-plus/icons-vue'
import { handleOAuthLoginCallback, storeAuthTokens, storeUserInfo } from '$/maimaitech/oauth2/api/loginApi'
import type { OAuthProviderName, OAuthLoginCallbackResponse } from '$/maimaitech/oauth2/api/types'

// Types
interface CallbackState {
  loading: boolean
  success: boolean
  error: boolean
  data?: OAuthLoginCallbackResponse['data']
  errorDetails?: string
}

interface ProgressState {
  value: number
  phase: 'validating' | 'exchanging' | 'authenticating' | 'completing'
}

interface RetryState {
  loading: boolean
  attempts: number
  maxAttempts: number
}

interface CallbackParams {
  provider: OAuthProviderName
  code: string
  state: string
  error?: string
  error_description?: string
}

// 使用从 types 导入的类型
// interface LoginCallbackResponse 现在使用 OAuthLoginCallbackResponse

// Composables
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

// Reactive state with proper typing
const callbackState = ref<CallbackState>({
  loading: true,
  success: false,
  error: false,
})

const progressState = ref<ProgressState>({
  value: 0,
  phase: 'validating',
})

const retryState = ref<RetryState>({
  loading: false,
  attempts: 0,
  maxAttempts: 3,
})

const redirectTarget = ref<string>('/')
const autoRedirectCountdown = ref<number>(0)
const errorDetails = ref<string>('')

// Timers for cleanup
let progressTimer: number | null = null
let redirectTimer: number | null = null
let countdownTimer: number | null = null

// Computed properties
const successMessage = computed((): string => {
  if (callbackState.value.data?.user?.username) {
    return `欢迎，${callbackState.value.data.user.nickname || callbackState.value.data.user.username}！`
  }
  return '登录成功！'
})

const errorMessage = computed((): string => {
  if (callbackState.value.errorDetails) {
    return getErrorTranslation(callbackState.value.errorDetails)
  }
  return '登录失败'
})

const progressAriaLabel = computed((): string => {
  const phase = progressState.value.phase
  const phaseTexts = {
    validating: '验证参数',
    exchanging: '交换令牌',
    authenticating: '验证身份',
    completing: '完成登录',
  }
  return `${phaseTexts[phase]}: ${Math.round(progressState.value.value)}%`
})

// Enhanced error handling with categorized errors
function getErrorTranslation(errorCode: string): string {
  const errorMap: Record<string, string> = {
    missing_provider: '缺少服务商参数',
    unsupported_provider: '不支持的服务商',
    invalid_state: '状态参数无效，可能存在安全风险',
    missing_code: '缺少授权码',
    exchange_failed: '获取访问令牌失败',
    user_info_failed: '获取用户信息失败',
    login_failed: '登录失败',
    network_error: '网络连接错误',
    timeout_error: '请求超时',
    validation_error: '参数验证失败',
    access_denied: '用户拒绝了授权请求',
    server_error: '服务器内部错误',
  }

  return errorMap[errorCode] || errorCode
}

// Enhanced progress simulation with phases
function simulateProgress(): number {
  progressTimer = window.setInterval(() => {
    if (progressState.value.value < 90) {
      // Different progress speeds for different phases
      const increment = progressState.value.phase === 'validating'
        ? 30
        : progressState.value.phase === 'exchanging'
          ? 20
          : progressState.value.phase === 'authenticating' ? 15 : 10

      progressState.value.value = Math.min(90, progressState.value.value + Math.random() * increment)

      // Update phase based on progress
      if (progressState.value.value > 25 && progressState.value.phase === 'validating') {
        progressState.value.phase = 'exchanging'
      }
      else if (progressState.value.value > 55 && progressState.value.phase === 'exchanging') {
        progressState.value.phase = 'authenticating'
      }
      else if (progressState.value.value > 80 && progressState.value.phase === 'authenticating') {
        progressState.value.phase = 'completing'
      }
    }
  }, 200)

  return progressTimer
}

// Enhanced parameter validation
function validateCallbackParams(): { isValid: boolean, error?: string, params?: CallbackParams } {
  const { code, state, error, error_description } = route.query
  const provider = route.params.provider as string

  // Check for provider parameter
  if (!provider) {
    return {
      isValid: false,
      error: 'missing_provider',
    }
  }

  // Validate provider is supported
  const supportedProviders: OAuthProviderName[] = ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq']
  if (!supportedProviders.includes(provider as OAuthProviderName)) {
    return {
      isValid: false,
      error: 'unsupported_provider',
    }
  }

  // Check for OAuth error parameters
  if (error) {
    return {
      isValid: false,
      error: (error_description as string) || (error as string) || 'access_denied',
    }
  }

  // Check for required parameters
  if (!code) {
    return {
      isValid: false,
      error: 'missing_code',
    }
  }

  if (!state) {
    return {
      isValid: false,
      error: 'invalid_state',
    }
  }

  // Validate parameter formats
  if (typeof code !== 'string' || code.length < 10) {
    return {
      isValid: false,
      error: 'validation_error',
    }
  }

  if (typeof state !== 'string' || state.length < 10) {
    return {
      isValid: false,
      error: 'validation_error',
    }
  }

  return {
    isValid: true,
    params: {
      provider: provider as OAuthProviderName,
      code: code as string,
      state: state as string,
      ...(error && { error: error as string }),
      ...(error_description && { error_description: error_description as string }),
    },
  }
}

// Handle login callback processing - 现在使用 API 方法
// 函数移动到 loginApi.ts 中，这里直接调用

// Enhanced callback processing with timeout and retry logic
async function handleCallbackProcessing(): Promise<void> {
  const progressTimerId = simulateProgress()

  try {
    // Phase 1: Validate parameters
    progressState.value.phase = 'validating'
    const validation = validateCallbackParams()
    if (!validation.isValid) {
      throw new Error(validation.error)
    }

    // Phase 2: Process login callback with timeout
    progressState.value.phase = 'exchanging'
    const timeoutPromise = new Promise<never>((_, reject) => {
      setTimeout(() => reject(new Error('timeout_error')), 30000) // 30 second timeout
    })

    const response = await Promise.race([
      handleOAuthLoginCallback(validation.params!.provider, validation.params!.code, validation.params!.state),
      timeoutPromise,
    ])

    // Phase 3: Store authentication tokens
    progressState.value.phase = 'authenticating'

    // Phase 4: Complete progress
    if (progressTimerId) {
      clearInterval(progressTimerId)
    }
    progressState.value.value = 100
    progressState.value.phase = 'completing'

    // Wait for visual feedback
    await new Promise(resolve => setTimeout(resolve, 500))

    // Update state to success
    callbackState.value = {
      loading: false,
      success: true,
      error: false,
      data: response.data,
    }

    // Set redirect target
    if (response.data?.redirect_url) {
      redirectTarget.value = response.data.redirect_url
    } else {
      // Default redirect to dashboard or home
      redirectTarget.value = '/dashboard'
    }

    // Show success message
    ElMessage.success(successMessage.value)

    // Start auto-redirect countdown
    startAutoRedirect()
  }
  catch (error: any) {
    if (progressTimerId) {
      clearInterval(progressTimerId)
    }

    console.error('OAuth login callback processing failed:', error)

    // Categorize errors for better user experience
    let errorCode = error.message || 'unknown_error'
    if (error.name === 'TypeError' || error.message?.includes('fetch')) {
      errorCode = 'network_error'
    } else if (error.message?.includes('timeout')) {
      errorCode = 'timeout_error'
    } else if (error.message?.includes('HTTP 5')) {
      errorCode = 'server_error'
    }

    callbackState.value = {
      loading: false,
      success: false,
      error: true,
      errorDetails: errorCode,
    }

    // Set detailed error info for debugging
    errorDetails.value = import.meta.env.DEV
      ? JSON.stringify({
          message: error.message,
          stack: error.stack,
          timestamp: new Date().toISOString(),
          url: window.location.href,
        }, null, 2)
      : ''

    ElMessage.error(errorMessage.value)
  }
}

// Auto-redirect functionality
function startAutoRedirect(): void {
  autoRedirectCountdown.value = 3
  countdownTimer = window.setInterval(() => {
    autoRedirectCountdown.value--
    if (autoRedirectCountdown.value <= 0) {
      if (countdownTimer) {
        clearInterval(countdownTimer)
      }
      redirectToTarget()
    }
  }, 1000)
}

function redirectToTarget(): void {
  // Clear any existing timers
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }

  try {
    // For login success, we might want to do a full page reload to ensure
    // the authentication state is properly initialized throughout the app
    window.location.href = redirectTarget.value
  }
  catch (error) {
    console.error('Navigation failed:', error)
    // Fallback to homepage
    window.location.href = '/'
  }
}

function goToLogin(): void {
  try {
    // router.push('/login')
  }
  catch (error) {
    console.error('Navigation to login failed:', error)
    // Fallback to reload login page
    window.location.href = '/login'
  }
}

// Enhanced retry with exponential backoff
async function retryCallback(): Promise<void> {
  if (retryState.value.attempts >= retryState.value.maxAttempts) {
    ElMessage.error('已达到最大重试次数，请返回重新登录')
    return
  }

  retryState.value.loading = true
  retryState.value.attempts++

  try {
    // Reset states
    callbackState.value = {
      loading: true,
      success: false,
      error: false,
    }
    progressState.value = {
      value: 0,
      phase: 'validating',
    }
    errorDetails.value = ''

    // Add delay for exponential backoff
    const delay = 2 ** (retryState.value.attempts - 1) * 1000
    await new Promise(resolve => setTimeout(resolve, delay))

    await nextTick()
    await handleCallbackProcessing()
  }
  catch (error) {
    console.error('Retry failed:', error)
  }
  finally {
    retryState.value.loading = false
  }
}

// Cleanup function
function cleanup(): void {
  if (progressTimer) {
    clearInterval(progressTimer)
    progressTimer = null
  }
  if (redirectTimer) {
    clearInterval(redirectTimer)
    redirectTimer = null
  }
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
}

// Get provider from route params (similar to existing callback)
function getProviderFromRoute(): OAuthProviderName | null {
  const provider = route.params.provider as string
  const supportedProviders: OAuthProviderName[] = ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq']

  if (provider && supportedProviders.includes(provider as OAuthProviderName)) {
    return provider as OAuthProviderName
  }

  return null
}

// Lifecycle hooks with proper cleanup
onMounted(() => {
  // Add visibility change listener to pause timers when page is hidden
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cleanup()
    }
    else if (callbackState.value.loading) {
      // Restart processing if page becomes visible and still loading
      handleCallbackProcessing()
    }
  })

  // Start processing immediately
  handleCallbackProcessing()
})

onUnmounted(() => {
  cleanup()
})

// Handle page refresh/close
window.addEventListener('beforeunload', cleanup)
</script>

<template>
  <div class="oauth-login-callback-container" role="main" aria-live="polite">
    <el-card class="callback-card" shadow="hover">
      <!-- Loading State -->
      <div v-if="callbackState.loading" class="callback-content">
        <div class="callback-icon" role="img" aria-label="正在登录">
          <el-icon class="rotating-icon" :size="48">
            <Loading />
          </el-icon>
        </div>
        <h2 class="callback-title">
          正在登录中...
        </h2>
        <p class="callback-description">
          请稍等，我们正在验证您的身份
        </p>
        <el-progress
          :percentage="progressState.value"
          :show-text="false"
          class="progress-bar"
          :aria-label="`登录进度 ${Math.round(progressState.value)}%`"
        />
        <div class="progress-phase">
          {{ progressAriaLabel }}
        </div>
        <div class="sr-only" aria-live="polite">
          {{ progressAriaLabel }}
        </div>
      </div>

      <!-- Success State -->
      <div v-else-if="callbackState.success" class="callback-content success">
        <div class="callback-icon" role="img" aria-label="登录成功">
          <el-icon :size="48" color="#67C23A">
            <SuccessFilled />
          </el-icon>
        </div>
        <h2 class="callback-title">
          登录成功！
        </h2>
        <p class="callback-description">
          {{ successMessage }}
        </p>
        <div v-if="callbackState.data?.user" class="user-info">
          <div class="user-avatar" v-if="callbackState.data.user.avatar">
            <img :src="callbackState.data.user.avatar" :alt="callbackState.data.user.username" />
          </div>
          <div class="user-details">
            <div class="user-name">{{ callbackState.data.user.nickname || callbackState.data.user.username }}</div>
            <div class="user-email" v-if="callbackState.data.user.email">{{ callbackState.data.user.email }}</div>
          </div>
        </div>
        <div class="callback-actions">
          <el-button
            type="primary"
            :aria-label="`即将跳转到${redirectTarget}`"
            @click="redirectToTarget"
          >
            立即前往
          </el-button>
        </div>
        <div v-if="autoRedirectCountdown > 0" class="auto-redirect-info">
          {{ autoRedirectCountdown }}秒后自动跳转
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="callbackState.error" class="callback-content error">
        <div class="callback-icon" role="img" aria-label="登录失败">
          <el-icon :size="48" color="#F56C6C">
            <CircleCloseFilled />
          </el-icon>
        </div>
        <h2 class="callback-title">
          登录失败
        </h2>
        <p class="callback-description">
          {{ errorMessage }}
        </p>
        <div v-if="errorDetails" class="error-details">
          <el-collapse>
            <el-collapse-item title="错误详情" name="details">
              <pre class="error-code">{{ errorDetails }}</pre>
            </el-collapse-item>
          </el-collapse>
        </div>
        <div class="callback-actions">
          <el-button @click="goToLogin">
            返回登录
          </el-button>
          <el-button
            type="primary"
            :loading="retryState.loading"
            :disabled="retryState.attempts >= retryState.maxAttempts"
            @click="retryCallback"
          >
            {{ retryState.attempts >= retryState.maxAttempts ? '已达最大重试' : '重试登录' }}
          </el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<style lang="scss" scoped>
.oauth-login-callback-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  padding: 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

  .callback-card {
    max-width: 500px;
    width: 100%;
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);

    :deep(.el-card__body) {
      padding: 48px 40px;
    }
  }

  .callback-content {
    text-align: center;

    .callback-icon {
      margin-bottom: 24px;

      .rotating-icon {
        animation: rotate 2s linear infinite;
      }
    }

    .callback-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 16px;
      color: var(--el-text-color-primary);
      line-height: 1.3;
    }

    .callback-description {
      font-size: 18px;
      color: var(--el-text-color-regular);
      margin-bottom: 32px;
      line-height: 1.6;
    }

    .progress-bar {
      margin-bottom: 16px;

      :deep(.el-progress-bar__outer) {
        border-radius: 12px;
        background-color: var(--el-fill-color-light);
        height: 8px;
      }

      :deep(.el-progress-bar__inner) {
        border-radius: 12px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.4s ease;
      }
    }

    .progress-phase {
      font-size: 14px;
      color: var(--el-text-color-secondary);
      margin-bottom: 24px;
    }

    .user-info {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin: 24px 0;
      padding: 20px;
      background: var(--el-fill-color-extra-light);
      border-radius: 12px;

      .user-avatar {
        img {
          width: 60px;
          height: 60px;
          border-radius: 50%;
          object-fit: cover;
          border: 3px solid var(--el-color-success);
        }
      }

      .user-details {
        text-align: left;

        .user-name {
          font-size: 18px;
          font-weight: 600;
          color: var(--el-text-color-primary);
          margin-bottom: 4px;
        }

        .user-email {
          font-size: 14px;
          color: var(--el-text-color-secondary);
        }
      }
    }

    .callback-actions {
      display: flex;
      justify-content: center;
      gap: 16px;
      flex-wrap: wrap;
      margin-top: 32px;

      .el-button {
        border-radius: 8px;
        padding: 12px 32px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
        min-width: 120px;

        &:focus-visible {
          outline: 3px solid var(--el-color-primary);
          outline-offset: 2px;
        }
      }
    }

    .auto-redirect-info {
      font-size: 14px;
      color: var(--el-text-color-secondary);
      margin-top: 16px;
      background: var(--el-fill-color-light);
      padding: 8px 16px;
      border-radius: 20px;
      display: inline-block;
    }

    .error-details {
      margin: 20px 0;
      text-align: left;

      .error-code {
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        font-size: 12px;
        background: var(--el-fill-color-light);
        padding: 16px;
        border-radius: 8px;
        overflow-x: auto;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 200px;
        overflow-y: auto;
      }
    }

    &.success {
      .callback-icon {
        animation: bounceIn 0.6s ease-out;
      }

      .callback-title {
        color: var(--el-color-success);
      }
    }

    &.error {
      .callback-icon {
        animation: shake 0.6s ease-out;
      }

      .callback-title {
        color: var(--el-color-danger);
      }

      .callback-description {
        color: var(--el-text-color-secondary);
      }
    }
  }
}

// Screen reader only content
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

// Animations with reduced motion support
@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes bounceIn {
  0% {
    transform: scale(0.3);
    opacity: 0;
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes shake {
  0%, 100% {
    transform: translateX(0);
  }
  10%, 30%, 50%, 70%, 90% {
    transform: translateX(-8px);
  }
  20%, 40%, 60%, 80% {
    transform: translateX(8px);
  }
}

// Disable animations for users who prefer reduced motion
@media (prefers-reduced-motion: reduce) {
  .rotating-icon {
    animation: none;
  }

  .callback-content.success .callback-icon {
    animation: none;
  }

  .callback-content.error .callback-icon {
    animation: none;
  }

  :deep(.el-progress-bar__inner) {
    transition: none;
  }
}

// Responsive design
@media (max-width: 768px) {
  .oauth-login-callback-container {
    padding: 16px;

    .callback-card {
      :deep(.el-card__body) {
        padding: 32px 24px;
      }
    }

    .callback-content {
      .callback-title {
        font-size: 24px;
      }

      .callback-description {
        font-size: 16px;
      }

      .user-info {
        flex-direction: column;
        text-align: center;

        .user-details {
          text-align: center;
        }
      }

      .callback-actions {
        flex-direction: column;

        .el-button {
          width: 100%;
          margin: 0;
        }
      }
    }
  }
}

// Dark mode support
@media (prefers-color-scheme: dark) {
  .oauth-login-callback-container {
    background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);

    .callback-card {
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }
  }
}

// High contrast mode support
@media (prefers-contrast: high) {
  .callback-card {
    border: 3px solid var(--el-border-color);
  }

  .callback-actions .el-button {
    border: 2px solid;
  }

  .user-info {
    border: 2px solid var(--el-border-color);
  }
}
</style>
