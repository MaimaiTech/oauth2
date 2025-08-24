import { computed } from 'vue'
import type { MaProTableSchema } from '@mineadmin/pro-table'
import { Copy, Link, Search, TestTube2 } from '@element-plus/icons-vue'
import type { OAuthProvider } from '../../../api/types'
import {
  getProviderBrandColor,
  getProviderDisplayName,
  getProviderOptions,
} from '../../../api/oauthApi'

interface SearchFormModel {
  name?: string
  enabled?: number | string
  keyword?: string
}

interface ProviderActionState {
  toggling: Set<number>
  testing: Set<number>
  deleting: Set<number>
}

export function useProviderTable(
  actionStates: ProviderActionState,
  handlers: {
    onEdit: (record: OAuthProvider) => void
    onDelete: (id: number) => void
    onTest: (id: number) => void
    onToggle: (id: number, enabled: boolean) => void
    onCopy: (text: string, label: string) => void
  },
) {
  const providerOptions = computed(() => getProviderOptions())

  // 表格列配置
  const tableColumns = computed(() => [
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
              onClick={() => handlers.onCopy(row.client_id, '客户端ID')}
            >
              {row.client_id.substring(0, 16)}
              ...
            </code>
          </el-tooltip>
          <el-button
            size="small"
            type="text"
            icon={Copy}
            onClick={() => handlers.onCopy(row.client_id, '客户端ID')}
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
              onClick={() => handlers.onCopy(row.redirect_uri, '回调地址')}
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
            {stats.last_used_at && (
              <div class="mt-1 text-xs text-gray-400">
                {new Date(stats.last_used_at).toLocaleDateString()}
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
          onChange={(enabled: boolean) => handlers.onToggle(row.id, enabled)}
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
              icon={TestTube2}
              loading={actionStates.testing.has(row.id)}
              disabled={isOperating}
              onClick={() => handlers.onTest(row.id)}
            >
              测试
            </el-button>
            <el-button
              v-auth={['oauth:provider:update']}
              size="small"
              type="primary"
              disabled={actionStates.deleting.has(row.id)}
              onClick={() => handlers.onEdit(row)}
            >
              编辑
            </el-button>
            <el-button
              v-auth={['oauth:provider:delete']}
              size="small"
              type="danger"
              loading={actionStates.deleting.has(row.id)}
              disabled={isOperating}
              onClick={() => handlers.onDelete(row.id)}
            >
              删除
            </el-button>
          </div>
        )
      },
    },
  ])

  // 搜索项配置
  const searchItems = computed(() => [
    {
      label: '提供者类型',
      prop: 'name',
      render: ({ model, field }: { model: SearchFormModel, field: keyof SearchFormModel }) => (
        <el-select
          v-model={model[field]}
          placeholder="选择提供者类型"
          clearable
          filterable
          class="w-full"
        >
          <el-option label="全部" value="" />
          {providerOptions.value.map((option: any) => (
            <el-option
              key={option.value}
              label={option.label}
              value={option.value}
            >
              {{
                default: () => (
                  <div class="flex items-center space-x-2">
                    <div
                      class="h-4 w-4 flex items-center justify-center rounded text-xs text-white"
                      style={{ backgroundColor: getProviderBrandColor(option.value) }}
                    >
                      {option.label.charAt(0)}
                    </div>
                    <span>{option.label}</span>
                  </div>
                ),
              }}
            </el-option>
          ))}
        </el-select>
      ),
    },
    {
      label: '启用状态',
      prop: 'enabled',
      render: ({ model, field }: { model: SearchFormModel, field: keyof SearchFormModel }) => (
        <el-select
          v-model={model[field]}
          placeholder="选择状态"
          clearable
          class="w-full"
        >
          <el-option label="全部" value="" />
          <el-option label="启用" value={1}>
            {{
              default: () => (
                <div class="flex items-center space-x-2">
                  <el-tag size="small" type="success">启用</el-tag>
                </div>
              ),
            }}
          </el-option>
          <el-option label="禁用" value={0}>
            {{
              default: () => (
                <div class="flex items-center space-x-2">
                  <el-tag size="small" type="danger">禁用</el-tag>
                </div>
              ),
            }}
          </el-option>
        </el-select>
      ),
    },
    {
      label: '关键词',
      prop: 'keyword',
      render: ({ model, field }: { model: SearchFormModel, field: keyof SearchFormModel }) => (
        <el-input
          v-model={model[field]}
          placeholder="搜索显示名称、客户端ID..."
          clearable
          class="w-full"
        >
          {{
            prefix: () => <el-icon><Search /></el-icon>,
          }}
        </el-input>
      ),
    },
  ])

  // 表格架构
  const schema = computed<MaProTableSchema>(() => ({
    searchItems: searchItems.value,
    tableColumns: tableColumns.value,
  }))

  return {
    providerOptions,
    tableColumns,
    searchItems,
    schema,
  }
}
