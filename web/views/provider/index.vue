<!--
 - OAuth2 Provider Management View - Fixed Version
 -
 - Optimized version without external dependencies
 - Features: Enhanced error handling, improved UX, performance optimized
-->
<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import { computed, reactive, ref, shallowRef } from 'vue'
import type { UseDialogExpose } from '@/hooks/useDialog.ts'
import type { OAuthProvider } from '../../api/types'

// ==================== 导入依赖 ====================
import {
  deleteProvider,
  deleteProviders,
  getProviderBrandColor,
  getProviderDisplayName,
  getProviderOptions,
  getProviders,
  testProvider,
  toggleProvider,
} from '../../api/oauthApi'
import ProviderForm from '../../components/ProviderForm.vue'
import useDialog from '@/hooks/useDialog.ts'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import { ElLoading, ElMessageBox } from 'element-plus'
import { Connection, Link, Plus } from '@element-plus/icons-vue'

// ==================== 类型定义 ====================
interface ProviderActionState {
  toggling: Set<number>
  testing: Set<number>
  deleting: Set<number>
}

defineOptions({ name: 'oauth2:provider:fixed' })

// ==================== 核心状态管理 ====================
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const formRef = ref()
const selections = shallowRef<OAuthProvider[]>([])
const msg = useMessage()

// 状态管理
const actionStates = reactive<ProviderActionState>({
  toggling: new Set(),
  testing: new Set(),
  deleting: new Set(),
})

// ==================== 工具函数 ====================
// 简单的防抖函数
function debounce<T extends (...args: any[]) => any>(func: T, wait: number): T {
  let timeout: ReturnType<typeof setTimeout>
  return ((...args: any[]) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => func.apply(null, args), wait)
  }) as T
}

// 统一的错误处理器
function handleError(error: any, context: string): string {
  console.error(`${context} Error:`, error)

  if (error?.response?.status === 422) {
    const validationErrors = error.response.data?.errors
    if (validationErrors) {
      return Object.values(validationErrors).flat().join('; ')
    }
  }

  const statusMessages: Record<number, string> = {
    403: '权限不足，请联系管理员',
    404: '资源不存在或已被删除',
    500: '服务器内部错误，请稍后重试',
  }

  const status = error?.response?.status
  if (status && statusMessages[status]) {
    return statusMessages[status]
  }

  return error?.response?.data?.message || error?.message || `${context}失败`
}

// 复制到剪贴板功能
async function copyToClipboard(text: string, label: string) {
  try {
    await navigator.clipboard.writeText(text)
    msg.success(`${label}已复制到剪贴板`)
  }
  catch (err) {
    console.error('复制失败:', err)
    msg.error('复制失败，请手动复制')
  }
}

// 防抖的切换状态函数
const debouncedToggle = debounce(async (id: number, enabled: boolean) => {
  if (actionStates.toggling.has(id)) { return }

  actionStates.toggling.add(id)
  try {
    await toggleProvider(id, enabled)
    msg.success(`${enabled ? '启用' : '禁用'}成功`)
    proTableRef.value.refresh()
  }
  catch (error: any) {
    msg.error(handleError(error, '状态切换'))
    proTableRef.value.refresh() // 刷新以恢复正确状态
  }
  finally {
    actionStates.toggling.delete(id)
  }
}, 300)

// ==================== 计算属性 ====================
const providerOptions = computed(() => getProviderOptions())

// 表格列配置
const getTableColumns = computed(() => [
  {
    type: 'selection',
    width: 50,
    selectable: (row: OAuthProvider) => !actionStates.deleting.has(row.id),
  },
  {
    label: '提供者',
    prop: 'name',
    width: 160,
    showOverflowTooltip: false,
    cellRender: ({ row }: { row: OAuthProvider }) => (
      <div class="flex items-center space-x-3">
        <div
          class="h-8 w-8 flex items-center justify-center rounded-lg text-sm text-white font-bold shadow-sm"
          style={{ backgroundColor: getProviderBrandColor(row.name) }}
          title={getProviderDisplayName(row.name)}
        >
          {getProviderDisplayName(row.name).charAt(0)}
        </div>
        <div class="flex flex-col">
          <span class="text-gray-900 font-medium">{getProviderDisplayName(row.name)}</span>
          <span class="text-xs text-gray-500">{row.name}</span>
        </div>
      </div>
    ),
  },
  {
    label: '显示名称',
    prop: 'display_name',
    width: 140,
    showOverflowTooltip: true,
  },
  {
    label: '客户端ID',
    prop: 'client_id',
    width: 200,
    showOverflowTooltip: false,
    cellRender: ({ row }: { row: OAuthProvider }) => (
      <div class="flex items-center space-x-2">
        <el-tooltip content="点击复制完整客户端ID" placement="top">
          <code
            class="cursor-pointer rounded bg-gray-100 px-2 py-1 text-sm font-mono transition-colors hover:bg-gray-200"
            onClick={() => copyToClipboard(row.client_id, '客户端ID')}
          >
            {row.client_id.substring(0, 16)}
            ...
          </code>
        </el-tooltip>
        <el-button
          size="small"
          type="text"
          onClick={() => copyToClipboard(row.client_id, '客户端ID')}
        />
      </div>
    ),
  },
  {
    label: '回调地址',
    prop: 'redirect_uri',
    width: 220,
    showOverflowTooltip: false,
    cellRender: ({ row }: { row: OAuthProvider }) => (
      <div class="flex items-center space-x-2">
        <el-tooltip content={row.redirect_uri} placement="top">
          <span
            class="max-w-[160px] cursor-pointer truncate text-sm text-blue-600 transition-colors hover:text-blue-800"
            onClick={() => copyToClipboard(row.redirect_uri, '回调地址')}
          >
            {row.redirect_uri}
          </span>
        </el-tooltip>
        <el-button
          size="small"
          type="text"
          icon={Link}
          onClick={() => window.open(row.redirect_uri, '_blank')}
          title="在新窗口打开"
        />
      </div>
    ),
  },
  {
    label: '授权范围',
    prop: 'scopes',
    width: 180,
    cellRender: ({ row }: { row: OAuthProvider }) => {
      const scopes = row.scopes || []
      const displayScopes = scopes.slice(0, 2)
      const remainingCount = scopes.length - 2

      return (
        <div class="flex flex-wrap gap-1">
          {displayScopes.map(scope => (
            <el-tag key={scope} size="small" type="info" effect="light">
              {scope}
            </el-tag>
          ))}
          {remainingCount > 0 && (
            <el-tooltip
              content={scopes.slice(2).join(', ')}
              placement="top"
            >
              <el-tag size="small" type="info" effect="plain">
                +
                {remainingCount}
              </el-tag>
            </el-tooltip>
          )}
        </div>
      )
    },
  },
  {
    label: '绑定统计',
    prop: 'stats',
    width: 120,
    align: 'center',
    cellRender: ({ row }: { row: OAuthProvider }) => {
      const stats = row.stats || { total_bindings: 0, active_bindings: 0 }
      return (
        <div class="text-center">
          <div class="text-lg text-blue-600 font-bold">
            {stats.total_bindings}
          </div>
          <div class="text-xs text-gray-500">
            活跃:
            {' '}
            {stats.active_bindings}
          </div>
          {(stats as any).last_used_at && (
            <div class="mt-1 text-xs text-gray-400">
              {new Date((stats as any).last_used_at).toLocaleDateString()}
            </div>
          )}
        </div>
      )
    },
  },
  {
    label: '状态',
    prop: 'enabled',
    width: 100,
    align: 'center',
    cellRender: ({ row }: { row: OAuthProvider }) => (
      <el-switch
        modelValue={row.enabled}
        onChange={(enabled: boolean) => debouncedToggle(row.id, enabled)}
        loading={actionStates.toggling.has(row.id)}
        activeColor="#13ce66"
        inactiveColor="#ff4949"
        disabled={actionStates.deleting.has(row.id)}
        activeText="启用"
        inactiveText="禁用"
        inlinePrompt
      />
    ),
  },
  {
    label: '排序',
    prop: 'sort',
    width: 80,
    align: 'center',
    sortable: true,
  },
  {
    label: '创建时间',
    prop: 'created_at',
    width: 160,
    sortable: true,
    cellRender: ({ row }: { row: OAuthProvider }) => (
      <div class="text-sm">
        <div>{new Date(row.created_at).toLocaleDateString()}</div>
        <div class="text-xs text-gray-500">
          {new Date(row.created_at).toLocaleTimeString()}
        </div>
      </div>
    ),
  },
  {
    label: '操作',
    width: 220,
    fixed: 'right',
    cellRender: ({ row }: { row: OAuthProvider }) => {
      const isOperating = actionStates.testing.has(row.id)
        || actionStates.toggling.has(row.id)
        || actionStates.deleting.has(row.id)

      return (
        <div class="flex space-x-1">
          <el-button
            v-auth={['oauth:provider:test']}
            size="small"
            type="info"
            loading={actionStates.testing.has(row.id)}
            disabled={isOperating}
            onClick={() => handleTestProvider(row.id)}
          >
            测试
          </el-button>
          <el-button
            v-auth={['oauth:provider:update']}
            size="small"
            type="primary"
            disabled={actionStates.deleting.has(row.id)}
            onClick={() => handleEdit(row)}
          >
            编辑
          </el-button>
          <el-button
            v-auth={['oauth:provider:delete']}
            size="small"
            type="danger"
            loading={actionStates.deleting.has(row.id)}
            disabled={isOperating}
            onClick={() => handleDelete(row.id)}
          >
            删除
          </el-button>
        </div>
      )
    },
  },
])

// 搜索项配置 - 使用标准的MineAdmin搜索项格式
const getSearchItems = computed(() => [
  {
    label: '提供者类型',
    prop: 'name',
    render: 'select',
    renderProps: {
      placeholder: '选择提供者类型',
      clearable: true,
      filterable: true,
      options: [
        { label: '全部', value: '' },
        ...providerOptions.value.map((option: any) => ({
          label: option.label,
          value: option.value,
        })),
      ],
    },
  },
  {
    label: '启用状态',
    prop: 'enabled',
    render: 'select',
    renderProps: {
      placeholder: '选择状态',
      clearable: true,
      options: [
        { label: '全部', value: '' },
        { label: '启用', value: 1 },
        { label: '禁用', value: 0 },
      ],
    },
  },
  {
    label: '关键词',
    prop: 'keyword',
    render: 'input',
    renderProps: {
      placeholder: '搜索显示名称、客户端ID...',
      clearable: true,
    },
  },
])

// ==================== 弹窗配置 ====================
const maDialog: UseDialogExpose = useDialog({
  ok: ({ formType }, okLoadingState: (state: boolean) => void) => {
    okLoadingState(true)
    if (['add', 'edit'].includes(formType)) {
      const elForm = formRef.value.maForm

      elForm.validate().then(() => {
        const action = formType === 'add' ? formRef.value.add() : formRef.value.edit()
        const successMessage = formType === 'add' ? 'OAuth提供者创建成功' : 'OAuth提供者更新成功'

        action.then((res: any) => {
          if (res.code === ResultCode.SUCCESS) {
            msg.success(successMessage)
            maDialog.close()
            proTableRef.value.refresh()
          }
          else {
            msg.error(res.message)
          }
        }).catch((err: any) => {
          msg.alertError(err)
        })
      }).catch((validationError: any) => {
        console.log('表单验证失败:', validationError)
      })
    }
    okLoadingState(false)
  },
})

// ==================== 表格参数配置 ====================
const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => 'OAuth2 提供者管理',
    subTitle: () => '管理第三方认证提供商配置',
  },
  tableOptions: {
    stripe: true,
    border: true,
    on: {
      onSelectionChange: (selection: OAuthProvider[]) => {
        selections.value = selection
      },
      onSortChange: (sort: any) => {
        proTableRef.value.setRequestParams({
          order: sort.prop,
          order_by_direction: sort.order === 'ascending' ? 'asc' : 'desc',
        }, true)
      },
    },
  },
  searchOptions: {
    fold: true,
    text: {
      searchBtn: () => '搜索',
      resetBtn: () => '重置',
      isFoldBtn: () => '收起',
      notFoldBtn: () => '展开',
    },
  },
  searchFormOptions: {
    labelWidth: '90px',
    size: 'default',
  },
  requestOptions: {
    api: getProviders,
  },
})

// 架构配置
const schema = computed<MaProTableSchema>(() => ({
  searchItems: getSearchItems.value,
  tableColumns: getTableColumns.value,
}))

// ==================== 业务逻辑函数 ====================
// 删除操作
function handleDelete(id?: number) {
  const ids = id ? [id] : selections.value.map((item: OAuthProvider) => item.id)
  const message = id
    ? '确定要删除这个OAuth提供者吗？删除后相关的用户绑定也会被清除。'
    : `确定要删除选中的 ${ids.length} 个OAuth提供者吗？删除后相关的用户绑定也会被清除。`

  // 检查是否有正在操作的项目
  const hasActiveOperations = ids.some((itemId: number) =>
    actionStates.testing.has(itemId) || actionStates.toggling.has(itemId) || actionStates.deleting.has(itemId),
  )

  if (hasActiveOperations) {
    msg.warning('请等待当前操作完成后再执行删除')
    return
  }

  ElMessageBox.confirm(message, '确认删除', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning',
    dangerouslyUseHTMLString: false,
  }).then(async () => {
    // 标记所有要删除的项目
    ids.forEach((itemId: number) => actionStates.deleting.add(itemId))

    try {
      if (id) {
        await deleteProvider(id)
      }
      else {
        await deleteProviders(ids)
      }
      msg.success('删除成功')
      proTableRef.value.refresh()

      // 清空选择
      if (!id) {
        selections.value = []
      }
    }
    catch (error: any) {
      msg.error(handleError(error, '删除操作'))
    }
    finally {
      ids.forEach((itemId: number) => actionStates.deleting.delete(itemId))
    }
  }).catch(() => {
    ids.forEach((itemId: number) => actionStates.deleting.delete(itemId))
  })
}

// 编辑操作
function handleEdit(record: OAuthProvider) {
  maDialog.setTitle('编辑OAuth提供者')
  maDialog.open({ formType: 'edit', data: record })
}

// 测试连接
async function handleTestProvider(id: number) {
  if (actionStates.testing.has(id)) { return }

  actionStates.testing.add(id)

  const loading = ElLoading.service({
    lock: true,
    text: '正在测试连接...',
    background: 'rgba(0, 0, 0, 0.7)',
  })

  try {
    const response = await testProvider(id)

    if (response.success) {
      const authUrl = response.data.auth_url

      msg.success('连接测试成功')

      if (authUrl) {
        ElMessageBox.confirm(
          '连接测试成功！是否要打开认证页面进行完整测试？',
          '测试成功',
          {
            confirmButtonText: '打开认证页面',
            cancelButtonText: '不用了',
            type: 'success',
          },
        ).then(() => {
          window.open(authUrl, '_blank')
        }).catch(() => {
          // 用户选择不打开
        })
      }
    }
    else {
      const errorDetail = response.data.error || '未知错误'
      msg.error(`连接测试失败: ${errorDetail}`)

      // 提供解决建议
      if (errorDetail.includes('client_id')) {
        msg.info('请检查客户端ID是否正确配置')
      }
      else if (errorDetail.includes('redirect_uri')) {
        msg.info('请检查回调地址是否正确配置')
      }
    }
  }
  catch (error: any) {
    msg.error(handleError(error, '连接测试'))
  }
  finally {
    loading.close()
    actionStates.testing.delete(id)
  }
}

// 批量操作
function handleBatchDelete() {
  if (selections.value.length === 0) {
    msg.warning('请选择要删除的OAuth提供者')
    return
  }
  handleDelete()
}

function handleBatchToggle(enabled: boolean) {
  if (selections.value.length === 0) {
    msg.warning(`请选择要${enabled ? '启用' : '禁用'}的OAuth提供者`)
    return
  }

  const action = enabled ? '启用' : '禁用'
  ElMessageBox.confirm(
    `确定要${action}选中的 ${selections.value.length} 个OAuth提供者吗？`,
    `批量${action}`,
    {
      confirmButtonText: `确定${action}`,
      cancelButtonText: '取消',
      type: 'warning',
    },
  ).then(async () => {
    const promises = selections.value.map((item: OAuthProvider) =>
      debouncedToggle(item.id, enabled),
    )

    try {
      await Promise.all(promises)
      msg.success(`批量${action}操作完成`)
    }
    catch (error) {
      msg.error(`批量${action}操作部分失败，请检查各项状态`)
    }
  })
}

function openAddDialog() {
  maDialog.setTitle('新增OAuth提供者')
  maDialog.open({ formType: 'add' })
}
</script>

<template>
  <div class="oauth-provider-container">
    <MaProTable ref="proTableRef" :options="options" :schema="schema">
      <!-- 顶部操作栏 -->
      <template #actions>
        <el-button
          v-auth="['oauth:provider:create']"
          type="primary"
          @click="openAddDialog"
        >
          <el-icon><Plus /></el-icon>
          新增提供者
        </el-button>
      </template>

      <!-- 工具栏左侧 -->
      <template #toolbarLeft>
        <el-button-group>
          <el-button
            v-auth="['oauth:provider:update']"
            type="success"
            plain
            :disabled="selections.length < 1"
            @click="handleBatchToggle(true)"
          >
            批量启用
          </el-button>
          <el-button
            v-auth="['oauth:provider:update']"
            type="warning"
            plain
            :disabled="selections.length < 1"
            @click="handleBatchToggle(false)"
          >
            批量禁用
          </el-button>
          <el-button
            v-auth="['oauth:provider:delete']"
            type="danger"
            plain
            :disabled="selections.length < 1"
            @click="handleBatchDelete"
          >
            批量删除
          </el-button>
        </el-button-group>
      </template>

      <!-- 数据为空时 -->
      <template #empty>
        <el-empty description="暂无OAuth提供者配置">
          <template #image>
            <el-icon size="60" color="#d0d7de">
              <Connection />
            </el-icon>
          </template>
          <el-button
            v-auth="['oauth:provider:create']"
            type="primary"
            @click="openAddDialog"
          >
            <el-icon><Plus /></el-icon>
            新增第一个提供者
          </el-button>
        </el-empty>
      </template>
    </MaProTable>

    <!-- 新增/编辑表单弹窗 -->
    <component :is="maDialog.Dialog">
      <template #default="{ formType, data }">
        <ProviderForm ref="formRef" :form-type="formType" :data="data" />
      </template>
    </component>
  </div>
</template>

<style scoped lang="scss">
.oauth-provider-container {
  padding: 16px;
  background: #f8fafc;
  min-height: calc(100vh - 120px);

  :deep(.el-table) {
    .cell {
      padding: 8px 12px;
    }

    .el-table__row:hover {
      background-color: #f8fafc;
    }
  }

  :deep(.el-button-group) {
    .el-button + .el-button {
      margin-left: 0;
    }
  }

  // 响应式适配
  @media (max-width: 768px) {
    padding: 8px;

    :deep(.el-table) {
      font-size: 12px;
    }
  }
}
</style>
