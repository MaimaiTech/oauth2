import { getProviderDisplayName, getProviderBrandColor } from '../../../api/oauthApi'

// Utility functions
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

export default function getTableColumns(handleForceUnbind: any, local: any) {
  return [
    {
      type: 'selection',
      width: 50,
    },
    {
      label: local('bindings.columns.userInfo') || '用户信息',
      prop: 'user',
      width: 180,
      cellRender: ({ row }) => (
        <div class="flex items-center space-x-3">
          <el-avatar 
            size="small" 
            src={row.user?.avatar || row.provider_avatar} 
            alt={`${row.user?.username || `用户${row.user_id}`}的头像`}
          >
            {(row.user?.username || `用户${row.user_id}`).charAt(0)}
          </el-avatar>
          <div>
            <div class="font-medium" title={row.user?.username || `用户${row.user_id}`}>
              {row.user?.username || `用户${row.user_id}`}
            </div>
            <div class="text-xs text-gray-500" title={row.user?.email}>
              {row.user?.email}
            </div>
          </div>
        </div>
      ),
    },
    {
      label: local('bindings.columns.provider') || 'OAuth提供者',
      prop: 'provider',
      width: 160,
      cellRender: ({ row }) => {
        const displayName = getProviderDisplayName(row.provider)
        const brandColor = getProviderBrandColor(row.provider)
        
        return (
          <div class="flex items-center space-x-2">
            <div
              class="w-6 h-6 rounded flex items-center justify-center text-white text-xs font-bold"
              style={{ backgroundColor: brandColor }}
              role="img"
              aria-label={`${displayName}图标`}
            >
              {displayName.charAt(0)}
            </div>
            <span class="font-medium">{displayName}</span>
          </div>
        )
      },
    },
    {
      label: local('bindings.columns.providerUser') || '第三方用户',
      prop: 'provider_user',
      width: 200,
      cellRender: ({ row }) => {
        const displayName = row.provider_nickname || row.provider_username
        
        return (
          <div class="space-y-1">
            <div class="flex items-center space-x-2">
              {row.provider_avatar && (
                <el-avatar 
                  size="small" 
                  src={row.provider_avatar}
                  alt={`${displayName}的头像`}
                />
              )}
              <div>
                <div class="font-medium" title={displayName}>
                  {displayName}
                </div>
                <div class="text-xs text-gray-500" title={`用户ID: ${row.provider_user_id}`}>
                  ID: {row.provider_user_id}
                </div>
              </div>
            </div>
          </div>
        )
      },
    },
    {
      label: local('bindings.columns.providerEmail') || '第三方邮箱',
      prop: 'provider_email',
      width: 180,
      cellRender: ({ row }) => (
        <span class="text-sm" title={row.provider_email}>
          {row.provider_email || '-'}
        </span>
      ),
    },
    {
      label: local('bindings.columns.status') || '状态',
      prop: 'status',
      width: 100,
      cellRender: ({ row }) => {
        const tagConfig = (() => {
          switch (row.status) {
            case 'normal': 
              return { type: 'success', text: '正常' }
            case 'disabled': 
              return { type: 'danger', text: '禁用' }
            default: 
              return { type: 'warning', text: '待激活' }
          }
        })()
        
        return (
          <el-tag type={tagConfig.type} size="small">
            {tagConfig.text}
          </el-tag>
        )
      },
    },
    {
      label: local('bindings.columns.tokenStatus') || '令牌状态',
      prop: 'token_status',
      width: 120,
      cellRender: ({ row }) => {
        const isExpired = row.expires_at && new Date(row.expires_at) < new Date()
        const statusConfig = {
          type: isExpired ? 'warning' : 'success',
          text: isExpired ? '已过期' : '有效',
        }
        
        return (
          <div class="text-center">
            <el-tag type={statusConfig.type} size="small">
              {statusConfig.text}
            </el-tag>
            {row.expires_at && (
              <div class="text-xs text-gray-500 mt-1">
                {formatDate(row.expires_at)}
              </div>
            )}
          </div>
        )
      },
    },
    {
      label: local('bindings.columns.lastLogin') || '最后登录',
      prop: 'last_login_at',
      width: 160,
      cellRender: ({ row }) => (
        <span class="text-sm" title={row.last_login_at ? formatDateTime(row.last_login_at) : '从未登录'}>
          {row.last_login_at ? formatDateTime(row.last_login_at) : '-'}
        </span>
      ),
    },
    {
      label: local('bindings.columns.createdAt') || '绑定时间',
      prop: 'created_at',
      width: 160,
      cellRender: ({ row }) => (
        <span class="text-sm" title={formatDateTime(row.created_at)}>
          {formatDateTime(row.created_at)}
        </span>
      ),
    },
    {
      label: local('common.actions') || '操作',
      width: 120,
      fixed: 'right',
      cellRender: ({ row }) => (
        <el-button
          v-auth={['oauth:binding:delete']}
          size="small"
          type="danger"
          onClick={() => handleForceUnbind(row.id)}
          aria-label={`解绑${row.user?.username || '用户'}的${getProviderDisplayName(row.provider)}账号`}
        >
          {local('bindings.actions.unbind') || '解绑'}
        </el-button>
      ),
    },
  ]
}