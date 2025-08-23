<!--
 - Optimized User OAuth Bindings Management View
 - 
 - Performance improvements:
 - 1. Computed table columns to prevent recreation
 - 2. Extracted cell components for better rendering performance
 - 3. Proper TypeScript types throughout
 - 4. Enhanced error handling and accessibility
 - 5. Internationalization support
 - 6. Memory leak prevention
-->
<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref, Component } from 'vue'
import type { TransType } from '@/hooks/auto-imports/useTrans.ts'
import type { UserOAuthAccount, UserBindingsQueryParams, BatchOperationRequest } from '../../api/types'
import type { ElLoadingService } from 'element-plus'

import { 
  getUserBindings, 
  forceUnbindAccount, 
  batchOperateBindings,
  exportBindings,
  getProviderOptions,
  getProviderDisplayName,
  getProviderBrandColor
} from '../../api/oauthApi'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

// Types
interface ExportState {
  loading: boolean
  progress: number
}

interface TableColumn {
  type?: string
  label?: string
  prop?: string
  width?: number
  fixed?: string | boolean
  render?: (params: { record: UserOAuthAccount }) => JSX.Element
}

defineOptions({ name: 'oauth2:bindings' })

// Reactive references
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<UserOAuthAccount[]>([])
const exportState = ref<ExportState>({
  loading: false,
  progress: 0
})

// Composables
const i18n = useTrans() as TransType
const t = i18n.globalTrans
const local = i18n.localTrans
const msg = useMessage()

// Utility functions - memoized for performance
const formatDateTime = (date: string | Date): string => {
  return new Intl.DateTimeFormat('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  }).format(new Date(date))
}

const formatDate = (date: string | Date, format = 'YYYY-MM-DD'): string => {
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  
  if (format === 'YYYY-MM-DD') {
    return `${year}-${month}-${day}`
  }
  return d.toLocaleDateString('zh-CN')
}

// Optimized cell components - extracted for better performance
const UserInfoCell = defineComponent({
  name: 'UserInfoCell',
  props: {
    record: {
      type: Object as PropType<UserOAuthAccount>,
      required: true
    }
  },
  setup(props) {
    const displayName = computed(() => 
      props.record.user?.username || `用户${props.record.user_id}`
    )
    const avatarSrc = computed(() => 
      props.record.user?.avatar || props.record.provider_avatar
    )
    
    return () => (
      <div class="flex items-center space-x-3">
        <el-avatar 
          size="small" 
          src={avatarSrc.value} 
          alt={displayName.value}
          aria-label={`${displayName.value}的头像`}
        >
          {displayName.value.charAt(0)}
        </el-avatar>
        <div>
          <div class="font-medium" title={displayName.value}>
            {displayName.value}
          </div>
          <div class="text-xs text-gray-500" title={props.record.user?.email}>
            {props.record.user?.email}
          </div>
        </div>
      </div>
    )
  }
})

const ProviderCell = defineComponent({
  name: 'ProviderCell',
  props: {
    record: {
      type: Object as PropType<UserOAuthAccount>,
      required: true
    }
  },
  setup(props) {
    const displayName = computed(() => getProviderDisplayName(props.record.provider))
    const brandColor = computed(() => getProviderBrandColor(props.record.provider))
    
    return () => (
      <div class="flex items-center space-x-2">
        <div
          class="w-6 h-6 rounded flex items-center justify-center text-white text-xs font-bold"
          style={{ backgroundColor: brandColor.value }}
          role="img"
          aria-label={`${displayName.value}图标`}
        >
          {displayName.value.charAt(0)}
        </div>
        <span class="font-medium">{displayName.value}</span>
      </div>
    )
  }
})

const ProviderUserCell = defineComponent({
  name: 'ProviderUserCell',
  props: {
    record: {
      type: Object as PropType<UserOAuthAccount>,
      required: true
    }
  },
  setup(props) {
    const displayName = computed(() => 
      props.record.provider_nickname || props.record.provider_username
    )
    
    return () => (
      <div class="space-y-1">
        <div class="flex items-center space-x-2">
          {props.record.provider_avatar && (
            <el-avatar 
              size="small" 
              src={props.record.provider_avatar}
              alt={`${displayName.value}的头像`}
            />
          )}
          <div>
            <div class="font-medium" title={displayName.value}>
              {displayName.value}
            </div>
            <div class="text-xs text-gray-500" title={`用户ID: ${props.record.provider_user_id}`}>
              ID: {props.record.provider_user_id}
            </div>
          </div>
        </div>
      </div>
    )
  }
})

const StatusTag = defineComponent({
  name: 'StatusTag',
  props: {
    status: {
      type: String,
      required: true
    }
  },
  setup(props) {
    const tagConfig = computed(() => {
      switch (props.status) {
        case 'normal': 
          return { type: 'success' as const, text: '正常', ariaLabel: '状态正常' }
        case 'disabled': 
          return { type: 'danger' as const, text: '禁用', ariaLabel: '状态已禁用' }
        default: 
          return { type: 'warning' as const, text: '待激活', ariaLabel: '状态待激活' }
      }
    })
    
    return () => (
      <el-tag 
        type={tagConfig.value.type} 
        size="small"
        aria-label={tagConfig.value.ariaLabel}
      >
        {tagConfig.value.text}
      </el-tag>
    )
  }
})

const TokenStatusCell = defineComponent({
  name: 'TokenStatusCell',
  props: {
    record: {
      type: Object as PropType<UserOAuthAccount>,
      required: true
    }
  },
  setup(props) {
    const isExpired = computed(() => 
      props.record.expires_at && new Date(props.record.expires_at) < new Date()
    )
    
    const statusConfig = computed(() => ({
      type: isExpired.value ? 'warning' as const : 'success' as const,
      text: isExpired.value ? '已过期' : '有效',
      ariaLabel: `令牌${isExpired.value ? '已过期' : '有效'}`
    }))
    
    return () => (
      <div class="text-center">
        <el-tag
          type={statusConfig.value.type}
          size="small"
          aria-label={statusConfig.value.ariaLabel}
        >
          {statusConfig.value.text}
        </el-tag>
        {props.record.expires_at && (
          <div class="text-xs text-gray-500 mt-1">
            {formatDate(props.record.expires_at)}
          </div>
        )}
      </div>
    )
  }
})

// Optimized table columns - computed to prevent recreation
const tableColumns = computed((): TableColumn[] => {
  return [
    {
      type: 'selection',
      width: 50,
    },
    {
      label: local('bindings.columns.userInfo') || '用户信息',
      prop: 'user',
      width: 180,
      render: ({ record }) => h(UserInfoCell, { record }),
    },
    {
      label: local('bindings.columns.provider') || 'OAuth提供者',
      prop: 'provider',
      width: 160,
      render: ({ record }) => h(ProviderCell, { record }),
    },
    {
      label: local('bindings.columns.providerUser') || '第三方用户',
      prop: 'provider_user',
      width: 200,
      render: ({ record }) => h(ProviderUserCell, { record }),
    },
    {
      label: local('bindings.columns.providerEmail') || '第三方邮箱',
      prop: 'provider_email',
      width: 180,
      render: ({ record }) => (
        <span class="text-sm" title={record.provider_email}>
          {record.provider_email || '-'}
        </span>
      ),
    },
    {
      label: local('bindings.columns.status') || '状态',
      prop: 'status',
      width: 100,
      render: ({ record }) => h(StatusTag, { status: record.status }),
    },
    {
      label: local('bindings.columns.tokenStatus') || '令牌状态',
      prop: 'token_status',
      width: 120,
      render: ({ record }) => h(TokenStatusCell, { record }),
    },
    {
      label: local('bindings.columns.lastLogin') || '最后登录',
      prop: 'last_login_at',
      width: 160,
      render: ({ record }) => (
        <span class="text-sm" title={record.last_login_at ? formatDateTime(record.last_login_at) : '从未登录'}>
          {record.last_login_at ? formatDateTime(record.last_login_at) : '-'}
        </span>
      ),
    },
    {
      label: local('bindings.columns.createdAt') || '绑定时间',
      prop: 'created_at',
      width: 160,
      render: ({ record }) => (
        <span class="text-sm" title={formatDateTime(record.created_at)}>
          {formatDateTime(record.created_at)}
        </span>
      ),
    },
    {
      label: local('common.actions') || '操作',
      width: 120,
      fixed: 'right',
      render: ({ record }) => (
        <el-button
          v-auth={['oauth:binding:delete']}
          size="small"
          type="danger"
          onClick={() => handleForceUnbind(record.id)}
          aria-label={`解绑${record.user?.username || '用户'}的${getProviderDisplayName(record.provider)}账号`}
        >
          {local('bindings.actions.unbind') || '解绑'}
        </el-button>
      ),
    },
  ]
})

// Optimized search items - computed for caching
const searchItems = computed(() => {
  const providerOptions = getProviderOptions()
  
  return [
    {
      label: local('bindings.search.provider') || '提供者',
      prop: 'provider',
      component: 'select',
      componentProps: {
        options: [
          { label: local('common.all') || '全部', value: '' },
          ...providerOptions.map(option => ({
            label: option.label,
            value: option.value,
          })),
        ],
        'aria-label': '选择OAuth提供者进行筛选',
      },
    },
    {
      label: local('bindings.search.status') || '状态',
      prop: 'status',
      component: 'select',
      componentProps: {
        options: [
          { label: local('common.all') || '全部', value: '' },
          { label: local('common.normal') || '正常', value: 'normal' },
          { label: local('common.disabled') || '禁用', value: 'disabled' },
          { label: local('common.pending') || '待激活', value: 'pending' },
        ],
        'aria-label': '选择状态进行筛选',
      },
    },
    {
      label: local('bindings.search.userId') || '用户ID',
      prop: 'user_id',
      component: 'input',
      componentProps: {
        placeholder: local('bindings.search.userIdPlaceholder') || '输入用户ID',
        type: 'number',
        'aria-label': '输入用户ID进行搜索',
      },
    },
    {
      label: local('bindings.search.username') || '用户名',
      prop: 'username',
      component: 'input',
      componentProps: {
        placeholder: local('bindings.search.usernamePlaceholder') || '搜索用户名',
        'aria-label': '输入用户名进行搜索',
      },
    },
    {
      label: local('bindings.search.providerUsername') || '第三方用户名',
      prop: 'provider_username',
      component: 'input',
      componentProps: {
        placeholder: local('bindings.search.providerUsernamePlaceholder') || '搜索第三方用户名',
        'aria-label': '输入第三方用户名进行搜索',
      },
    },
    {
      label: local('bindings.search.dateRange') || '绑定时间',
      prop: 'date_range',
      component: 'date-picker',
      componentProps: {
        type: 'daterange',
        rangeSeparator: local('common.to') || '至',
        startPlaceholder: local('common.startDate') || '开始日期',
        endPlaceholder: local('common.endDate') || '结束日期',
        format: 'YYYY-MM-DD',
        valueFormat: 'YYYY-MM-DD',
        'aria-label': '选择绑定时间范围进行筛选',
      },
    },
  ] as const
})

// Pro table options with optimized event handlers
const options = computed<MaProTableOptions>(() => ({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => local('bindings.title') || '用户OAuth绑定管理',
  },
  tableOptions: {
    on: {
      onSelectionChange: (selection: UserOAuthAccount[]) => {
        selections.value = selection
      },
      onSortChange: (sort: any) => {
        if (proTableRef.value) {
          proTableRef.value.setRequestParams({
            order: sort.prop,
            order_by_direction: sort.order === 'ascending' ? 'asc' : 'desc',
          }, true)
        }
      },
    },
  },
  searchOptions: {
    fold: true,
    text: {
      searchBtn: () => local('common.search') || '搜索',
      resetBtn: () => local('common.reset') || '重置',
      isFoldBtn: () => local('common.collapse') || '收起',
      notFoldBtn: () => local('common.expand') || '展开',
    },
  },
  searchFormOptions: { labelWidth: '90px' },
  requestOptions: {
    api: getUserBindings,
    requestParamsHandler: (params: any): UserBindingsQueryParams => {
      // Handle date range
      if (params.date_range && params.date_range.length === 2) {
        params.date_from = params.date_range[0]
        params.date_to = params.date_range[1]
        delete params.date_range
      }
      return params
    },
  },
}))

// Schema configuration - computed for performance
const schema = computed<MaProTableSchema>(() => ({
  searchItems: searchItems.value,
  tableColumns: tableColumns.value,
}))

// Enhanced error handling with specific error types
class BindingError extends Error {
  constructor(message: string, public code?: string, public details?: any) {
    super(message)
    this.name = 'BindingError'
  }
}

// Optimized action handlers with proper error handling
const handleForceUnbind = async (id: number): Promise<void> => {
  const confirmMessage = local('bindings.confirm.forceUnbind') || 
    '确定要强制解绑这个OAuth账号吗？解绑后用户将无法通过此第三方账号登录。'
  
  try {
    await msg.confirm(confirmMessage)
    await forceUnbindAccount(id)
    
    msg.success(local('bindings.success.unbind') || '强制解绑成功')
    proTableRef.value?.refresh()
  } catch (error: any) {
    if (error.message !== 'cancel') { // User cancelled
      console.error('Force unbind failed:', error)
      msg.error(error.message || local('bindings.error.unbind') || '解绑失败')
    }
  }
}

const handleBatchOperation = async (action: 'unbind' | 'disable'): Promise<void> => {
  if (selections.value.length === 0) {
    msg.warning(local('bindings.warning.noSelection') || '请选择要操作的OAuth账号')
    return
  }
  
  const actionText = action === 'unbind' ? '解绑' : '禁用'
  const message = local(`bindings.confirm.batch${action.charAt(0).toUpperCase() + action.slice(1)}`) ||
    `确定要批量${actionText}选中的 ${selections.value.length} 个OAuth账号吗？`
  
  try {
    await msg.confirm(message)
    
    const bindingIds = selections.value.map(item => item.id)
    const requestData: BatchOperationRequest = {
      action,
      binding_ids: bindingIds,
      reason: `管理员批量${actionText}操作`
    }
    
    await batchOperateBindings(requestData)
    
    msg.success(local(`bindings.success.batch${action.charAt(0).toUpperCase() + action.slice(1)}`) || 
      `批量${actionText}成功`)
    proTableRef.value?.refresh()
  } catch (error: any) {
    if (error.message !== 'cancel') {
      console.error(`Batch ${action} failed:`, error)
      msg.error(error.message || local(`bindings.error.batch${action.charAt(0).toUpperCase() + action.slice(1)}`) ||
        `批量${actionText}失败`)
    }
  }
}

const handleBatchUnbind = (): Promise<void> => handleBatchOperation('unbind')
const handleBatchDisable = (): Promise<void> => handleBatchOperation('disable')

// Enhanced export function with proper resource management
const downloadFile = (url: string): Promise<void> => {
  return new Promise((resolve, reject) => {
    const link = document.createElement('a')
    link.href = url
    link.download = `oauth-bindings-${formatDate(new Date(), 'YYYY-MM-DD')}.csv`
    
    const handleError = () => {
      document.body.removeChild(link)
      reject(new BindingError('Download failed', 'DOWNLOAD_ERROR'))
    }
    
    const handleSuccess = () => {
      document.body.removeChild(link)
      resolve()
    }
    
    link.addEventListener('error', handleError, { once: true })
    
    document.body.appendChild(link)
    link.click()
    
    // Assume download starts immediately for file:// or blob: URLs
    setTimeout(handleSuccess, 100)
  })
}

const handleExport = async (): Promise<void> => {
  if (exportState.value.loading) {
    msg.warning(local('bindings.export.inProgress') || '导出正在进行中...')
    return
  }
  
  exportState.value.loading = true
  exportState.value.progress = 0
  
  let loadingInstance: ReturnType<typeof ElLoading.service> | null = null
  let progressTimer: number | null = null
  
  try {
    loadingInstance = ElLoading.service({
      lock: true,
      text: local('bindings.export.preparing') || '正在准备导出数据...',
      background: 'rgba(0, 0, 0, 0.7)',
    })
    
    const searchParams = proTableRef.value?.getRequestParams() || {}
    
    // Progress simulation
    progressTimer = window.setInterval(() => {
      if (exportState.value.progress < 80) {
        exportState.value.progress += Math.random() * 10
      }
    }, 200)
    
    const response = await exportBindings({
      format: 'csv',
      ...searchParams,
    })
    
    if (progressTimer) {
      clearInterval(progressTimer)
      progressTimer = null
    }
    
    exportState.value.progress = 100
    
    if (response.data.download_url) {
      await downloadFile(response.data.download_url)
      msg.success(local('bindings.export.success') || '导出成功')
    } else {
      throw new BindingError('No download URL provided', 'NO_DOWNLOAD_URL')
    }
  } catch (error: any) {
    console.error('Export failed:', error)
    msg.error(error.message || local('bindings.export.failed') || '导出失败')
  } finally {
    if (progressTimer) {
      clearInterval(progressTimer)
    }
    if (loadingInstance) {
      loadingInstance.close()
    }
    exportState.value.loading = false
    exportState.value.progress = 0
  }
}

const handleRefresh = (): void => {
  proTableRef.value?.refresh()
  msg.success(local('common.refreshSuccess') || '数据已刷新')
}

// Batch operation handler for dropdown
const handleBatchCommand = (command: string): void => {
  switch (command) {
    case 'unbind':
      handleBatchUnbind()
      break
    case 'disable':
      handleBatchDisable()
      break
    default:
      console.warn('Unknown batch command:', command)
  }
}
</script>

<template>
  <div class="mine-layout pt-3" role="main" aria-label="OAuth绑定管理页面">
    <MaProTable 
      ref="proTableRef" 
      :options="options" 
      :schema="schema"
      aria-label="OAuth绑定数据表格"
    >
      <template #actions>
        <el-button
          type="primary"
          @click="handleRefresh"
          :aria-label="local('common.refresh') || '刷新数据'"
        >
          {{ local('common.refresh') || '刷新数据' }}
        </el-button>
        <el-button
          v-auth="['oauth:binding:export']"
          type="info"
          @click="handleExport"
          :loading="exportState.loading"
          :aria-label="local('bindings.export.button') || '导出数据'"
        >
          {{ local('bindings.export.button') || '导出数据' }}
        </el-button>
      </template>

      <template #toolbarLeft>
        <el-dropdown 
          @command="handleBatchCommand"
          :aria-label="local('bindings.batchActions') || '批量操作'"
        >
          <el-button
            type="danger"
            plain
            :disabled="selections.length < 1"
            :aria-label="`批量操作，当前选中${selections.length}项`"
          >
            {{ local('bindings.batchActions') || '批量操作' }}
            <el-icon class="el-icon--right">
              <i class="i-solar:alt-arrow-down-outline" />
            </el-icon>
          </el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item 
                command="unbind"
                v-auth="['oauth:binding:delete']"
              >
                {{ local('bindings.actions.batchUnbind') || '批量解绑' }}
              </el-dropdown-item>
              <el-dropdown-item 
                command="disable"
                v-auth="['oauth:binding:batch']"
              >
                {{ local('bindings.actions.batchDisable') || '批量禁用' }}
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </template>

      <template #empty>
        <el-empty 
          :description="local('bindings.empty.description') || '暂无OAuth绑定记录'"
          :aria-label="local('bindings.empty.description') || '暂无OAuth绑定记录'"
        >
          <el-button 
            type="primary" 
            @click="handleRefresh"
            :aria-label="local('common.refresh') || '刷新数据'"
          >
            {{ local('common.refresh') || '刷新数据' }}
          </el-button>
        </el-empty>
      </template>
    </MaProTable>
  </div>
</template>

<style scoped lang="scss">
.mine-layout {
  padding: 16px;
  background: #f5f5f5;
  min-height: calc(100vh - 120px);

  // Focus styles for accessibility
  :deep(.el-button:focus-visible) {
    outline: 2px solid var(--el-color-primary);
    outline-offset: 2px;
  }

  // Loading state improvements
  :deep(.el-loading-mask) {
    backdrop-filter: blur(2px);
  }
}

// Responsive improvements
@media (max-width: 768px) {
  .mine-layout {
    padding: 8px;
  }
}

// High contrast mode support
@media (prefers-contrast: high) {
  :deep(.el-tag) {
    border: 2px solid;
  }
}

// Reduced motion support
@media (prefers-reduced-motion: reduce) {
  :deep(.el-loading-spinner) {
    animation: none;
  }
}
</style>