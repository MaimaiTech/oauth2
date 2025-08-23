 <template>
  <div class="oauth-callback-container" role="main" aria-live="polite">
    <el-card class="callback-card" shadow="hover">
      <!-- Loading State -->
      <div v-if="callbackState.loading" class="callback-content">
        <div class="callback-icon" role="img" aria-label="正在处理">
          <el-icon class="rotating-icon" :size="48">
            <Loading />
          </el-icon>
        </div>
        <h2 class="callback-title">{{ $t('oauth2.callback.processing') }}</h2>
        <p class="callback-description">
          {{ $t('oauth2.common.loading') }}
        </p>
        <el-progress
          :percentage="progressState.value"
          :show-text="false"
          class="progress-bar"
          :aria-label="`处理进度 ${Math.round(progressState.value)}%`"
        />
        <div class="sr-only" aria-live="polite">
          {{ progressAriaLabel }}
        </div>
      </div>

      <!-- Success State -->
      <div v-else-if="callbackState.success" class="callback-content success">
        <div class="callback-icon" role="img" aria-label="成功">
          <el-icon :size="48" color="#67C23A">
            <SuccessFilled />
          </el-icon>
        </div>
        <h2 class="callback-title">{{ $t('oauth2.callback.success') }}</h2>
        <p class="callback-description">
          {{ successMessage }}
        </p>
        <div class="callback-actions">
          <el-button
            type="primary"
            @click="redirectToTarget"
            :aria-label="`即将跳转到${redirectTarget}`"
          >
            {{ $t('oauth2.callback.redirecting') }}
          </el-button>
        </div>
        <div v-if="autoRedirectCountdown > 0" class="auto-redirect-info">
          {{ autoRedirectCountdown }}秒后自动跳转
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="callbackState.error" class="callback-content error">
        <div class="callback-icon" role="img" aria-label="错误">
          <el-icon :size="48" color="#F56C6C">
            <CircleCloseFilled />
          </el-icon>
        </div>
        <h2 class="callback-title">{{ $t('oauth2.callback.error') }}</h2>
        <p class="callback-description">
          {{ errorMessage }}
        </p>
        <div class="error-details" v-if="errorDetails">
          <el-collapse>
            <el-collapse-item title="错误详情" name="details">
              <pre class="error-code">{{ errorDetails }}</pre>
            </el-collapse-item>
          </el-collapse>
        </div>
        <div class="callback-actions">
          <el-button @click="goBack">
            {{ $t('oauth2.common.back') }}
          </el-button>
          <el-button
            type="primary"
            @click="retryCallback"
            :loading="retryState.loading"
          >
            {{ $t('oauth2.common.retry') }}
          </el-button>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Loading, SuccessFilled, CircleCloseFilled } from '@element-plus/icons-vue'
import { handleOAuthCallback } from '../../api/userOAuthApi'
import type { OAuthCallbackResponse, OAuthProviderName } from '../../api/types'

// Types
interface CallbackState {
  loading: boolean
  success: boolean
  error: boolean
  data?: OAuthCallbackResponse['data']
  errorDetails?: string
}

interface ProgressState {
  value: number
  phase: 'validating' | 'exchanging' | 'processing' | 'completing'
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

// Composables
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

// Reactive state with proper typing
const callbackState = ref<CallbackState>({
  loading: true,
  success: false,
  error: false
})

const progressState = ref<ProgressState>({
  value: 0,
  phase: 'validating'
})

const retryState = ref<RetryState>({
  loading: false,
  attempts: 0,
  maxAttempts: 3
})

const redirectTarget = ref<string>('/personal/oauth-bindings')
const autoRedirectCountdown = ref<number>(0)
const errorDetails = ref<string>('')

// Timers for cleanup
let progressTimer: number | null = null
let redirectTimer: number | null = null
let countdownTimer: number | null = null

// Computed properties
const successMessage = computed((): string => {
  if (callbackState.value.data?.message) {
    return callbackState.value.data.message
  }
  return t('oauth2.personal.messages.bindSuccess')
})

const errorMessage = computed((): string => {
  if (callbackState.value.errorDetails) {
    return getErrorTranslation(callbackState.value.errorDetails)
  }
  return t('oauth2.personal.messages.bindFailed')
})

const progressAriaLabel = computed((): string => {
  const phase = progressState.value.phase
  const phaseTexts = {
    validating: '验证参数',
    exchanging: '交换令牌',
    processing: '处理用户信息',
    completing: '完成绑定'
  }
  return `${phaseTexts[phase]}: ${Math.round(progressState.value.value)}%`
})

// Enhanced error handling with categorized errors
const getErrorTranslation = (errorCode: string): string => {
  const errorMap: Record<string, string> = {
    'missing_provider': t('oauth2.callback.errors.missingProvider') || '缺少服务商参数',
    'unsupported_provider': t('oauth2.callback.errors.unsupportedProvider') || '不支持的服务商',
    'invalid_state': t('oauth2.callback.errors.invalidState'),
    'missing_code': t('oauth2.callback.errors.missingCode'),
    'exchange_failed': t('oauth2.callback.errors.exchangeFailed'),
    'user_info_failed': t('oauth2.callback.errors.userInfoFailed'),
    'binding_failed': t('oauth2.callback.errors.bindingFailed'),
    'network_error': t('oauth2.callback.errors.networkError'),
    'timeout_error': t('oauth2.callback.errors.timeoutError'),
    'validation_error': t('oauth2.callback.errors.validationError')
  }

  return errorMap[errorCode] || errorCode
}

// Enhanced progress simulation with phases
const simulateProgress = (): number => {
  progressTimer = window.setInterval(() => {
    if (progressState.value.value < 90) {
      // Different progress speeds for different phases
      const increment = progressState.value.phase === 'validating' ? 25 :
                       progressState.value.phase === 'exchanging' ? 15 :
                       progressState.value.phase === 'processing' ? 10 : 5

      progressState.value.value = Math.min(90, progressState.value.value + Math.random() * increment)

      // Update phase based on progress
      if (progressState.value.value > 20 && progressState.value.phase === 'validating') {
        progressState.value.phase = 'exchanging'
      } else if (progressState.value.value > 50 && progressState.value.phase === 'exchanging') {
        progressState.value.phase = 'processing'
      } else if (progressState.value.value > 80 && progressState.value.phase === 'processing') {
        progressState.value.phase = 'completing'
      }
    }
  }, 300)

  return progressTimer
}

// Enhanced parameter validation
const validateCallbackParams = (): { isValid: boolean; error?: string; params?: CallbackParams } => {
  const { code, state, error, error_description } = route.query
  const provider = route.params.provider as string

  // Check for provider parameter
  if (!provider) {
    return {
      isValid: false,
      error: 'missing_provider'
    }
  }

  // Validate provider is supported
  const supportedProviders: OAuthProviderName[] = ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq']
  if (!supportedProviders.includes(provider as OAuthProviderName)) {
    return {
      isValid: false,
      error: 'unsupported_provider'
    }
  }

  // Check for OAuth error parameters
  if (error) {
    return {
      isValid: false,
      error: (error_description as string) || (error as string) || 'oauth_error'
    }
  }

  // Check for required parameters
  if (!code) {
    return {
      isValid: false,
      error: 'missing_code'
    }
  }

  if (!state) {
    return {
      isValid: false,
      error: 'invalid_state'
    }
  }

  // Validate parameter formats
  if (typeof code !== 'string' || code.length < 10) {
    return {
      isValid: false,
      error: 'validation_error'
    }
  }

  if (typeof state !== 'string' || state.length < 10) {
    return {
      isValid: false,
      error: 'validation_error'
    }
  }

  return {
    isValid: true,
    params: {
      provider: provider as OAuthProviderName,
      code: code as string,
      state: state as string,
      ...(error && { error: error as string }),
      ...(error_description && { error_description: error_description as string })
    }
  }
}

// Enhanced callback processing with timeout and retry logic
const handleCallbackProcessing = async (): Promise<void> => {
  const progressTimerId = simulateProgress()

  try {
    // Phase 1: Validate parameters
    progressState.value.phase = 'validating'
    const validation = validateCallbackParams()
    if (!validation.isValid) {
      throw new Error(validation.error)
    }

    // Phase 2: Process callback with timeout
    progressState.value.phase = 'exchanging'
    const timeoutPromise = new Promise<never>((_, reject) => {
      setTimeout(() => reject(new Error('timeout_error')), 30000) // 30 second timeout
    })

    const response = await Promise.race([
      handleOAuthCallback(validation.params!.provider, validation.params!.code, validation.params!.state),
      timeoutPromise
    ])

    // Phase 3: Complete progress
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
      data: response.data
    }

    // Set redirect target
    if (response.data?.redirect_url) {
      redirectTarget.value = response.data.redirect_url
    }

    // Show success message
    ElMessage.success(successMessage.value)

    // Start auto-redirect countdown
    startAutoRedirect()

  } catch (error: any) {
    if (progressTimerId) {
      clearInterval(progressTimerId)
    }

    console.error('OAuth callback processing failed:', error)

    // Categorize errors for better user experience
    let errorCode = error.message || 'unknown_error'
    if (error.name === 'TypeError' || error.message?.includes('fetch')) {
      errorCode = 'network_error'
    }

    callbackState.value = {
      loading: false,
      success: false,
      error: true,
      errorDetails: errorCode
    }

    // Set detailed error info for debugging
    errorDetails.value = import.meta.env.DEV ? JSON.stringify({
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString(),
      url: window.location.href
    }, null, 2) : ''

    ElMessage.error(errorMessage.value)
  }
}

// Auto-redirect functionality
const startAutoRedirect = (): void => {
  autoRedirectCountdown.value = 5
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

const redirectToTarget = (): void => {
  // Clear any existing timers
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }

  try {
    router.push(redirectTarget.value)
  } catch (error) {
    console.error('Navigation failed:', error)
    // Fallback to page refresh
    window.location.href = redirectTarget.value
  }
}

const goBack = (): void => {
  try {
    router.back()
  } catch (error) {
    console.error('Navigation back failed:', error)
    // Fallback to home page
    router.push('/')
  }
}

// Enhanced retry with exponential backoff
const retryCallback = async (): Promise<void> => {
  if (retryState.value.attempts >= retryState.value.maxAttempts) {
    ElMessage.error('已达到最大重试次数，请刷新页面重试')
    return
  }

  retryState.value.loading = true
  retryState.value.attempts++

  try {
    // Reset states
    callbackState.value = {
      loading: true,
      success: false,
      error: false
    }
    progressState.value = {
      value: 0,
      phase: 'validating'
    }
    errorDetails.value = ''

    // Add delay for exponential backoff
    const delay = Math.pow(2, retryState.value.attempts - 1) * 1000
    await new Promise(resolve => setTimeout(resolve, delay))

    await nextTick()
    await handleCallbackProcessing()
  } catch (error) {
    console.error('Retry failed:', error)
  } finally {
    retryState.value.loading = false
  }
}

// Cleanup function
const cleanup = (): void => {
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

// Lifecycle hooks with proper cleanup
onMounted(() => {
  // Add visibility change listener to pause timers when page is hidden
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cleanup()
    } else if (callbackState.value.loading) {
      // Restart processing if page becomes visible and still loading
      handleCallbackProcessing()
    }
  })

  // Start processing immediately
  handleCallbackProcessing()

  const urlObj = new URL(window.location.href);
  const code = urlObj.searchParams.get("code") || urlObj.searchParams.get("authCode");
  const state = urlObj.searchParams.get("state");
  const hashPath = urlObj.hash.split("?")[0]; // 原始 Hash 路径

  if (code && state) {
    const fixedUrl = `${urlObj.origin}${hashPath}?code=${code}&state=${state}`;
    window.location.href = fixedUrl;
  }
})

onUnmounted(() => {
  cleanup()
})

// Handle page refresh/close
window.addEventListener('beforeunload', cleanup)
</script>

<style lang="scss" scoped>
.oauth-callback-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  padding: 20px;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);

  .callback-card {
    max-width: 500px;
    width: 100%;
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);

    :deep(.el-card__body) {
      padding: 40px 30px;
    }
  }

  .callback-content {
    text-align: center;

    .callback-icon {
      margin-bottom: 20px;

      .rotating-icon {
        animation: rotate 2s linear infinite;
      }
    }

    .callback-title {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 12px;
      color: var(--el-text-color-primary);
    }

    .callback-description {
      font-size: 16px;
      color: var(--el-text-color-regular);
      margin-bottom: 24px;
      line-height: 1.6;
    }

    .progress-bar {
      margin-bottom: 20px;

      :deep(.el-progress-bar__outer) {
        border-radius: 10px;
        background-color: var(--el-fill-color-light);
      }

      :deep(.el-progress-bar__inner) {
        border-radius: 10px;
        background: linear-gradient(90deg, #409eff 0%, #67c23a 100%);
        transition: width 0.3s ease;
      }
    }

    .callback-actions {
      display: flex;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;

      .el-button {
        border-radius: 6px;
        padding: 10px 24px;
        font-weight: 500;
        transition: all 0.2s ease;

        &:focus-visible {
          outline: 2px solid var(--el-color-primary);
          outline-offset: 2px;
        }
      }
    }

    .auto-redirect-info {
      font-size: 14px;
      color: var(--el-text-color-secondary);
      margin-top: 12px;
    }

    .error-details {
      margin: 16px 0;
      text-align: left;

      .error-code {
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        font-size: 12px;
        background: var(--el-fill-color-light);
        padding: 12px;
        border-radius: 4px;
        overflow-x: auto;
        white-space: pre-wrap;
        word-break: break-word;
      }
    }

    &.success {
      .callback-icon {
        animation: scaleIn 0.5s ease-out;
      }

      .callback-title {
        color: var(--el-color-success);
      }
    }

    &.error {
      .callback-icon {
        animation: shake 0.5s ease-out;
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

@keyframes scaleIn {
  from {
    transform: scale(0.5);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

@keyframes shake {
  0%, 100% {
    transform: translateX(0);
  }
  10%, 30%, 50%, 70%, 90% {
    transform: translateX(-5px);
  }
  20%, 40%, 60%, 80% {
    transform: translateX(5px);
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
  .oauth-callback-container {
    padding: 10px;

    .callback-card {
      :deep(.el-card__body) {
        padding: 30px 20px;
      }
    }

    .callback-content {
      .callback-title {
        font-size: 20px;
      }

      .callback-description {
        font-size: 14px;
      }

      .callback-actions {
        flex-direction: column;

        .el-button {
          width: 100%;
        }
      }
    }
  }
}

// Dark mode support
@media (prefers-color-scheme: dark) {
  .oauth-callback-container {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);

    .callback-card {
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
  }
}

// High contrast mode support
@media (prefers-contrast: high) {
  .callback-card {
    border: 2px solid var(--el-border-color);
  }

  .callback-actions .el-button {
    border: 2px solid;
  }
}
</style>
