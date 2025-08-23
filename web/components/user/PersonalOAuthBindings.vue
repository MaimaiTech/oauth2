<template>
  <div class="personal-oauth-bindings">
    <!-- Header Section with Statistics -->
    <el-card class="header-card" shadow="never">
      <el-row :gutter="24" align="middle">
        <el-col :xs="24" :sm="16" :md="18">
          <div class="header-content">
            <div class="title-section">
              <el-icon class="title-icon"><Link /></el-icon>
              <div>
                <h1 class="page-title">第三方账号绑定</h1>
                <p class="page-subtitle">管理您的OAuth账号连接</p>
              </div>
            </div>
            <div class="stats-section">
              <el-badge :value="bindings.length" :max="99" class="stats-badge">
                <el-button type="success" size="small" circle>
                  <el-icon><CircleCheckFilled /></el-icon>
                </el-button>
              </el-badge>
              <span class="stats-text">已连接</span>
              <el-badge :value="unboundProviders.length" :max="99" class="stats-badge">
                <el-button type="primary" size="small" circle>
                  <el-icon><Plus /></el-icon>
                </el-button>
              </el-badge>
              <span class="stats-text">可绑定</span>
            </div>
          </div>
        </el-col>
        <el-col :xs="24" :sm="8" :md="6">
          <div class="header-actions">
            <el-tooltip content="刷新连接状态" placement="top">
              <el-button 
                type="primary" 
                :icon="Refresh" 
                :loading="refreshing"
                @click="handleRefresh"
              >
                刷新
              </el-button>
            </el-tooltip>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- Loading State with Skeletons -->
    <div v-if="loading && !bindings.length" class="loading-container">
      <el-row :gutter="24">
        <el-col v-for="i in 4" :key="i" :xs="24" :sm="12" :lg="6">
          <el-card shadow="never" class="skeleton-card">
            <el-skeleton :loading="true" animated>
              <template #template>
                <div class="skeleton-header">
                  <el-skeleton-item variant="circle" style="width: 48px; height: 48px" />
                  <div class="skeleton-info">
                    <el-skeleton-item variant="text" style="width: 120px; height: 18px" />
                    <el-skeleton-item variant="text" style="width: 80px; height: 14px" />
                  </div>
                </div>
                <div class="skeleton-content">
                  <el-skeleton-item variant="text" style="width: 100%; height: 14px" />
                  <el-skeleton-item variant="text" style="width: 85%; height: 14px" />
                </div>
                <div class="skeleton-actions">
                  <el-skeleton-item variant="button" style="width: 80px; height: 32px" />
                  <el-skeleton-item variant="button" style="width: 80px; height: 32px" />
                </div>
              </template>
            </el-skeleton>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <!-- Empty State -->
    <el-empty
      v-else-if="!loading && bindings.length === 0 && availableProviders.length === 0"
      description="暂无可用的第三方OAuth服务"
      class="empty-container"
    >
      <template #image>
        <el-icon class="empty-icon"><Connection /></el-icon>
      </template>
      <el-button type="primary" :icon="Refresh" @click="handleRefresh">
        重新检查
      </el-button>
    </el-empty>

    <!-- Main Content Layout -->
    <div v-else class="content-container">
      <!-- Connected Accounts Section -->
      <el-card v-if="bindings.length > 0" class="section-card" shadow="never">
        <template #header>
          <div class="section-header">
            <el-icon class="section-icon"><Connection /></el-icon>
            <span class="section-title">已连接账号</span>
            <el-badge :value="bindings.length" :max="99" class="section-badge" />
          </div>
        </template>
        
        <el-row :gutter="24">
          <el-col
            v-for="binding in bindings"
            :key="binding.id"
            :xs="24"
            :sm="12"
            :lg="8"
            :xl="6"
          >
            <BindingCard
              :binding="binding"
              :loading="loadingBindings.includes(binding.id)"
              @refresh="handleBindingRefresh"
              @unbind="handleBindingUnbind"
              @updated="handleBindingUpdated"
            />
          </el-col>
        </el-row>
      </el-card>

      <!-- Available Providers Section -->
      <el-card v-if="unboundProviders.length > 0" class="section-card" shadow="never">
        <template #header>
          <div class="section-header">
            <el-icon class="section-icon"><Plus /></el-icon>
            <span class="section-title">可绑定服务</span>
            <el-badge :value="unboundProviders.length" :max="99" class="section-badge" type="primary" />
          </div>
        </template>

        <el-row :gutter="24">
          <el-col
            v-for="provider in unboundProviders"
            :key="provider.name"
            :xs="24"
            :sm="12"
            :md="8"
            :lg="6"
          >
            <el-card class="provider-card" shadow="hover">
              <div class="provider-header">
                <div class="provider-avatar" :style="{ backgroundColor: provider.brand_color || '#409eff' }">
                  <el-icon :size="24">
                    <component :is="getProviderIcon(provider.name)" />
                  </el-icon>
                </div>
                <div class="provider-info">
                  <h4 class="provider-name">{{ provider.display_name }}</h4>
                  <StatusIndicator
                    status="disconnected"
                    :provider="provider.name"
                    :show-text="true"
                    :show-tooltip="false"
                    :icon-size="12"
                  />
                </div>
              </div>

              <p class="provider-description">{{ getProviderDescription(provider.name) }}</p>

              <div class="provider-actions">
                <el-button
                  type="primary"
                  :loading="bindingProviders.includes(provider.name)"
                  :disabled="bindingProviders.includes(provider.name)"
                  @click="handleBindProvider(provider.name)"
                  block
                >
                  <template #icon>
                    <el-icon><Link /></el-icon>
                  </template>
                  {{ bindingProviders.includes(provider.name) ? '连接中...' : `连接 ${provider.display_name}` }}
                </el-button>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-card>

      <!-- Security Information Section -->
      <el-card class="security-card" shadow="never">
        <template #header>
          <div class="section-header">
            <el-icon class="section-icon"><InfoFilled /></el-icon>
            <span class="section-title">安全提示</span>
          </div>
        </template>

        <el-row :gutter="24">
          <el-col :xs="24" :sm="12" :lg="6" v-for="tip in securityTips" :key="tip.title">
            <div class="tip-item">
              <div class="tip-icon">
                <el-icon :size="20">
                  <component :is="tip.icon" />
                </el-icon>
              </div>
              <div class="tip-content">
                <h5 class="tip-title">{{ tip.title }}</h5>
                <p class="tip-description">{{ tip.description }}</p>
              </div>
            </div>
          </el-col>
        </el-row>
      </el-card>
    </div>

    <!-- Enhanced OAuth Flow Dialog -->
    <el-dialog
      v-model="showOAuthDialog"
      :title="`${currentBindingProvider ? getProviderDisplayName(currentBindingProvider) : 'OAuth'} 授权`"
      width="600px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      class="oauth-dialog"
      align-center
    >
      <div class="oauth-dialog-content">
        <div class="oauth-loading-indicator">
          <el-icon class="is-loading oauth-spinner"><Loading /></el-icon>
          <p>正在跳转到授权页面...</p>
        </div>
        <OAuthFlowHandler
          v-if="currentBindingProvider"
          :auto-start="false"
          success-redirect=""
          error-redirect=""
          @success="handleOAuthSuccess"
          @error="handleOAuthError"
          @complete="handleOAuthComplete"
        />
      </div>
    </el-dialog>

    <!-- Success Toast for Better Feedback -->
    <Teleport to="body">
      <Transition name="success-toast">
        <div v-if="showSuccessToast" class="success-toast">
          <el-icon class="success-icon"><CircleCheckFilled /></el-icon>
          <span>{{ successMessage }}</span>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Link,
  Refresh,
  Loading,
  Connection,
  Plus,
  CircleCheckFilled,
  InfoFilled,
  Shield,
  Key,
  Setting,
  Notification
} from '@element-plus/icons-vue'

import type { OAuthProviderName } from '../../api/types'
import type { UserOAuthBinding, AvailableProvider } from '../../api/userOAuthApi'
import {
  getCurrentUserBindings,
  getAvailableProviders,
  authorizeProvider,
  unbindAccount,
  refreshToken,
  getProviderIcon,
  storeOAuthState,
  generateOAuthState
} from '../../api/userOAuthApi'

import BindingCard from '../oauth/BindingCard.vue'
import StatusIndicator from '../oauth/StatusIndicator.vue'
import OAuthFlowHandler from '../oauth/OAuthFlowHandler.vue'

// Component state
const loading = ref(true)
const refreshing = ref(false)
const bindings = ref<UserOAuthBinding[]>([])
const availableProviders = ref<AvailableProvider[]>([])
const loadingBindings = ref<number[]>([])
const bindingProviders = ref<OAuthProviderName[]>([])

// OAuth flow state
const showOAuthDialog = ref(false)
const currentBindingProvider = ref<OAuthProviderName | null>(null)

// Enhanced UI state
const showSuccessToast = ref(false)
const successMessage = ref('')

// Security tips data
const securityTips = ref([
  {
    title: '安全绑定',
    description: '所有OAuth连接都经过加密保护，确保您的数据安全',
    icon: 'Shield'
  },
  {
    title: '快速登录',
    description: '绑定后可使用第三方账号一键登录，提升使用体验',
    icon: 'Key'
  },
  {
    title: '随时管理',
    description: '可随时解绑账号，不会影响您的现有数据',
    icon: 'Setting'
  },
  {
    title: '同步信息',
    description: '自动同步头像和基本信息，保持资料最新',
    icon: 'Notification'
  }
])

// Computed properties
const unboundProviders = computed(() => {
  return availableProviders.value.filter(provider => 
    provider.enabled && !provider.is_bound
  )
})


// Lifecycle
onMounted(() => {
  loadData()
})

// Load all data
const loadData = async () => {
  try {
    loading.value = true
    await Promise.all([
      loadBindings(),
      loadAvailableProviders()
    ])
  } catch (error: any) {
    ElMessage.error('加载数据失败: ' + (error.message || '未知错误'))
  } finally {
    loading.value = false
  }
}

// Load user bindings
const loadBindings = async () => {
  try {
    const response = await getCurrentUserBindings()
    bindings.value = response.data || []
  } catch (error: any) {
    console.error('Failed to load bindings:', error)
    throw error
  }
}

// Load available providers
const loadAvailableProviders = async () => {
  try {
    const response = await getAvailableProviders()
    availableProviders.value = response.data || []
  } catch (error: any) {
    console.error('Failed to load providers:', error)
    throw error
  }
}

// Handle refresh
const handleRefresh = async () => {
  try {
    refreshing.value = true
    await loadData()
    showSuccessMessage('所有连接状态已刷新')
  } catch (error: any) {
    ElMessage.error('刷新失败: ' + (error.message || '未知错误'))
  } finally {
    refreshing.value = false
  }
}

// Handle bind provider
const handleBindProvider = async (provider: OAuthProviderName) => {
  try {
    bindingProviders.value.push(provider)
    
    // Generate and store OAuth state
    const state = generateOAuthState()
    storeOAuthState(provider, state)
    
    // Get authorization URL
    const response = await authorizeProvider(provider, window.location.href)
    
    if (response.data.auth_url) {
      // Redirect to OAuth provider
      window.location.href = response.data.auth_url
    } else {
      throw new Error('未获取到授权链接')
    }
  } catch (error: any) {
    bindingProviders.value = bindingProviders.value.filter(p => p !== provider)
    ElMessage.error('启动授权失败: ' + (error.message || '未知错误'))
  }
}

// Handle binding refresh
const handleBindingRefresh = async (binding: UserOAuthBinding) => {
  try {
    loadingBindings.value.push(binding.id)
    await refreshToken(binding.provider)
    await loadBindings()
    showSuccessMessage(`${binding.provider_config.display_name} 令牌已刷新`)
  } catch (error: any) {
    ElMessage.error('令牌刷新失败: ' + (error.message || '未知错误'))
  } finally {
    loadingBindings.value = loadingBindings.value.filter(id => id !== binding.id)
  }
}

// Handle binding unbind
const handleBindingUnbind = async (binding: UserOAuthBinding) => {
  try {
    await ElMessageBox.confirm(
      `确定要解绑 ${binding.provider_config.display_name} 账号吗？解绑后将无法使用该账号快速登录。`,
      '解绑确认',
      {
        confirmButtonText: '确定解绑',
        cancelButtonText: '取消',
        type: 'warning',
        confirmButtonClass: 'el-button--danger'
      }
    )

    loadingBindings.value.push(binding.id)
    await unbindAccount(binding.provider)
    await loadData()
    showSuccessMessage(`${binding.provider_config.display_name} 已成功解绑`)
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error('解绑失败: ' + (error.message || '未知错误'))
    }
  } finally {
    loadingBindings.value = loadingBindings.value.filter(id => id !== binding.id)
  }
}

// Handle binding updated
const handleBindingUpdated = async () => {
  await loadData()
}

// OAuth flow handlers
const handleOAuthSuccess = (result: any) => {
  showSuccessMessage('第三方账号绑定成功！')
  loadData()
}

const handleOAuthError = (error: string) => {
  ElMessage.error('账号绑定失败: ' + error)
}

const handleOAuthComplete = () => {
  showOAuthDialog.value = false
  currentBindingProvider.value = null
  if (bindingProviders.value.length > 0) {
    bindingProviders.value = []
  }
}

// Enhanced UI helper methods
const getProviderDescription = (provider: OAuthProviderName): string => {
  const descriptions = {
    dingtalk: '企业级办公平台，支持团队协作和即时通讯',
    github: '全球最大的代码托管平台，开发者社区',
    gitee: '国内领先的代码托管和协作开发平台',
    feishu: '字节跳动出品的企业协作与管理平台',
    wechat: '微信生态，连接十亿用户的社交平台',
    qq: '腾讯QQ，经典的即时通讯社交平台'
  }
  return descriptions[provider] || '第三方服务平台'
}

const getProviderDisplayName = (provider: OAuthProviderName): string => {
  const names = {
    dingtalk: '钉钉',
    github: 'GitHub', 
    gitee: 'Gitee',
    feishu: '飞书',
    wechat: '微信',
    qq: 'QQ'
  }
  return names[provider] || provider
}

const showSuccessMessage = (message: string) => {
  successMessage.value = message
  showSuccessToast.value = true
  setTimeout(() => {
    showSuccessToast.value = false
  }, 3000)
}
</script>

<style lang="scss" scoped>
.personal-oauth-bindings {
  min-height: 100vh;
  background-color: var(--el-bg-color-page);
  padding: 20px;
}

// Header Card Styles
.header-card {
  margin-bottom: 24px;
  border: 1px solid var(--el-border-color-lighter);
}

.header-content {
  .title-section {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;

    .title-icon {
      font-size: 32px;
      color: var(--el-color-primary);
    }

    .page-title {
      margin: 0 0 4px;
      font-size: 24px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }

    .page-subtitle {
      margin: 0;
      font-size: 14px;
      color: var(--el-text-color-regular);
    }
  }

  .stats-section {
    display: flex;
    align-items: center;
    gap: 16px;

    .stats-badge {
      margin-right: 8px;
    }

    .stats-text {
      font-size: 14px;
      color: var(--el-text-color-regular);
      margin-right: 24px;
    }
  }
}

.header-actions {
  display: flex;
  justify-content: flex-end;
}

// Loading Container Styles
.loading-container {
  margin: 24px 0;
}

.skeleton-card {
  border: 1px solid var(--el-border-color-lighter);
  
  .skeleton-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;

    .skeleton-info {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
  }

  .skeleton-content {
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .skeleton-actions {
    display: flex;
    gap: 8px;
  }
}

// Empty State Styles
.empty-container {
  margin: 48px 0;
  
  .empty-icon {
    font-size: 64px;
    color: var(--el-color-info-light-3);
  }
}

// Content Container Styles
.content-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-card {
  border: 1px solid var(--el-border-color-lighter);

  .section-header {
    display: flex;
    align-items: center;
    gap: 12px;

    .section-icon {
      font-size: 20px;
      color: var(--el-color-primary);
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }

    .section-badge {
      margin-left: auto;
    }
  }
}

// Provider Card Styles
.provider-card {
  height: 100%;
  border: 1px solid var(--el-border-color-lighter);
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: var(--el-box-shadow-light);
  }

  .provider-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;

    .provider-avatar {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
    }

    .provider-info {
      flex: 1;

      .provider-name {
        margin: 0 0 4px;
        font-size: 16px;
        font-weight: 600;
        color: var(--el-text-color-primary);
      }
    }
  }

  .provider-description {
    margin: 0 0 16px;
    font-size: 14px;
    color: var(--el-text-color-regular);
    line-height: 1.5;
  }

  .provider-actions {
    margin-top: auto;
  }
}

// Security Card Styles
.security-card {
  border: 1px solid var(--el-border-color-lighter);

  .tip-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    background-color: var(--el-fill-color-lighter);
    transition: all 0.3s ease;

    &:hover {
      background-color: var(--el-fill-color);
      transform: translateY(-1px);
    }

    .tip-icon {
      color: var(--el-color-primary);
      flex-shrink: 0;
      margin-top: 2px;
    }

    .tip-content {
      .tip-title {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 600;
        color: var(--el-text-color-primary);
      }

      .tip-description {
        margin: 0;
        font-size: 13px;
        color: var(--el-text-color-regular);
        line-height: 1.4;
      }
    }
  }
}

// Success Toast Styles
.success-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background-color: var(--el-color-success);
  color: white;
  border-radius: 4px;
  box-shadow: var(--el-box-shadow);
  font-size: 14px;
  z-index: 3000;

  .success-icon {
    font-size: 16px;
  }
}

// Responsive Design
@media (max-width: 768px) {
  .personal-oauth-bindings {
    padding: 16px;
  }

  .header-content {
    .title-section {
      flex-direction: column;
      align-items: flex-start;
      text-align: left;

      .page-title {
        font-size: 20px;
      }
    }

    .stats-section {
      flex-wrap: wrap;
    }
  }

  .header-actions {
    width: 100%;
    margin-top: 16px;

    :deep(.el-button) {
      width: 100%;
    }
  }

  .success-toast {
    left: 16px;
    right: 16px;
    top: 16px;
  }
}

// Transition Effects
.success-toast-enter-active,
.success-toast-leave-active {
  transition: all 0.3s ease;
}

.success-toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.success-toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>