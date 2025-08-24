import { getProviderOptions } from '../../../api/oauthApi'

export default function getSearchItems(local: any) {
  const providerOptions = getProviderOptions()
  
  return [
    {
      label: local('bindings.search.provider') || '提供者',
      prop: 'provider',
      render: 'select',
      options: [
        { label: local('common.all') || '全部', value: '' },
        ...providerOptions.map(option => ({
          label: option.label,
          value: option.value,
        })),
      ],
    },
    {
      label: local('bindings.search.status') || '状态',
      prop: 'status',
      render: 'select',
      options: [
        { label: local('common.all') || '全部', value: '' },
        { label: local('common.normal') || '正常', value: 'normal' },
        { label: local('common.disabled') || '禁用', value: 'disabled' },
        { label: local('common.pending') || '待激活', value: 'pending' },
      ],
    },
    {
      label: local('bindings.search.userId') || '用户ID',
      prop: 'user_id',
      render: 'input',
      renderProps: {
        placeholder: local('bindings.search.userIdPlaceholder') || '输入用户ID',
        type: 'number',
      },
    },
    {
      label: local('bindings.search.username') || '用户名',
      prop: 'username',
      render: 'input',
      renderProps: {
        placeholder: local('bindings.search.usernamePlaceholder') || '搜索用户名',
      },
    },
    {
      label: local('bindings.search.providerUsername') || '第三方用户名',
      prop: 'provider_username',
      render: 'input',
      renderProps: {
        placeholder: local('bindings.search.providerUsernamePlaceholder') || '搜索第三方用户名',
      },
    },
    {
      label: local('bindings.search.dateRange') || '绑定时间',
      prop: 'date_range',
      render: 'date-picker',
      renderProps: {
        type: 'daterange',
        rangeSeparator: local('common.to') || '至',
        startPlaceholder: local('common.startDate') || '开始日期',
        endPlaceholder: local('common.endDate') || '结束日期',
        format: 'YYYY-MM-DD',
        valueFormat: 'YYYY-MM-DD',
      },
    },
  ]
}