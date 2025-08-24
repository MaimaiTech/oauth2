<!--
 - MineAdmin OAuth User Bindings Management
 - Simplified version following the working permission/user/index.vue pattern
-->
<script setup lang="tsx">
import type { MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { Ref } from 'vue'
import type { TransType } from '@/hooks/auto-imports/useTrans.ts'

import {
  batchOperateBindings,
  exportBindings,
  forceUnbindAccount,
  getUserBindings,
} from '../../api/oauthApi'
import getSearchItems from './data/getSearchItems.tsx'
import getTableColumns from './data/getTableColumns.tsx'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'

defineOptions({ name: 'oauth2:bindings' })

// Reactive references
const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<any[]>([])

// Composables
const i18n = useTrans() as TransType
const local = i18n.localTrans
const msg = useMessage()

// Pro table options
const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => local('bindings.title') || '用户OAuth绑定管理',
    subTitle: () => '管理用户的第三方OAuth账号绑定关系',
  },
  tableOptions: {
    on: {
      onSelectionChange: (selection: any[]) => {
        selections.value = selection
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
    requestParamsHandler: (params: any) => {
      // Handle date range
      if (params.date_range && params.date_range.length === 2) {
        params.date_from = params.date_range[0]
        params.date_to = params.date_range[1]
        delete params.date_range
      }
      return params
    },
  },
})

// Schema configuration
const schema = ref<MaProTableSchema>({
  searchItems: getSearchItems(local),
  tableColumns: getTableColumns(handleForceUnbind, local),
})

// Action handlers
async function handleForceUnbind(id: number): Promise<void> {
  const confirmMessage = local('bindings.confirm.forceUnbind')
    || '确定要强制解绑这个OAuth账号吗？解绑后用户将无法通过此第三方账号登录。'

  try {
    await msg.confirm(confirmMessage)
    const response = await forceUnbindAccount(id)
    if (response.code === ResultCode.SUCCESS) {
      msg.success(local('bindings.success.unbind') || '强制解绑成功')
      proTableRef.value?.refresh()
    }
  }
  catch (error: any) {
    if (error.message !== 'cancel') {
      console.error('Force unbind failed:', error)
      msg.error(error.message || local('bindings.error.unbind') || '解绑失败')
    }
  }
}

async function handleBatchOperation(action: 'unbind' | 'disable'): Promise<void> {
  if (selections.value.length === 0) {
    msg.warning(local('bindings.warning.noSelection') || '请选择要操作的OAuth账号')
    return
  }

  const actionText = action === 'unbind' ? '解绑' : '禁用'
  const message = local(`bindings.confirm.batch${action.charAt(0).toUpperCase() + action.slice(1)}`)
    || `确定要批量${actionText}选中的 ${selections.value.length} 个OAuth账号吗？`

  try {
    await msg.confirm(message)

    const bindingIds = selections.value.map(item => item.id)
    const requestData = {
      action,
      binding_ids: bindingIds,
      reason: `管理员批量${actionText}操作`,
    }

    const response = await batchOperateBindings(requestData)
    if (response.code === ResultCode.SUCCESS) {
      msg.success(local(`bindings.success.batch${action.charAt(0).toUpperCase() + action.slice(1)}`)
        || `批量${actionText}成功`)
      proTableRef.value?.refresh()
    }
  }
  catch (error: any) {
    if (error.message !== 'cancel') {
      console.error(`Batch ${action} failed:`, error)
      msg.error(error.message || local(`bindings.error.batch${action.charAt(0).toUpperCase() + action.slice(1)}`)
        || `批量${actionText}失败`)
    }
  }
}

const handleBatchUnbind = (): Promise<void> => handleBatchOperation('unbind')
const handleBatchDisable = (): Promise<void> => handleBatchOperation('disable')

async function handleExport(): Promise<void> {
  try {
    const searchParams = proTableRef.value?.getRequestParams() || {}
    const response = await exportBindings({
      format: 'csv',
      ...searchParams,
    })

    if (response.data.download_url) {
      const link = document.createElement('a')
      link.href = response.data.download_url
      link.download = `oauth-bindings-${new Date().toISOString().split('T')[0]}.csv`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      msg.success(local('bindings.export.success') || '导出成功')
    }
  }
  catch (error: any) {
    console.error('Export failed:', error)
    msg.error(error.message || local('bindings.export.failed') || '导出失败')
  }
}

function handleRefresh(): void {
  proTableRef.value?.refresh()
  msg.success(local('common.refreshSuccess') || '数据已刷新')
}

function handleBatchCommand(command: string): void {
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
          :aria-label="local('common.refresh') || '刷新数据'"
          @click="handleRefresh"
        >
          {{ local('common.refresh') || '刷新数据' }}
        </el-button>
        <el-button
          v-auth="['oauth:binding:export']"
          type="info"
          :aria-label="local('bindings.export.button') || '导出数据'"
          @click="handleExport"
        >
          {{ local('bindings.export.button') || '导出数据' }}
        </el-button>
      </template>

      <template #toolbarLeft>
        <el-dropdown
          :aria-label="local('bindings.batchActions') || '批量操作'"
          @command="handleBatchCommand"
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
                v-auth="['oauth:binding:delete']"
                command="unbind"
              >
                {{ local('bindings.actions.batchUnbind') || '批量解绑' }}
              </el-dropdown-item>
              <el-dropdown-item
                v-auth="['oauth:binding:batch']"
                command="disable"
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
        >
          <el-button
            type="primary"
            :aria-label="local('common.refresh') || '刷新数据'"
            @click="handleRefresh"
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
}

// Responsive improvements
@media (max-width: 768px) {
  .mine-layout {
    padding: 8px;
  }
}
</style>
