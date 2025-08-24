import { reactive } from 'vue'
import { debounce } from 'lodash-es'
import { ElLoading, ElMessageBox } from 'element-plus'
import { useMessage } from '@/hooks/useMessage.ts'
import type { OAuthProvider } from '../../../api/types'
import {
  deleteProvider,
  deleteProviders,
  testProvider,
  toggleProvider,
} from '../../../api/oauthApi'

interface ProviderActionState {
  toggling: Set<number>
  testing: Set<number>
  deleting: Set<number>
}

export function useProviderActions() {
  const msg = useMessage()

  // 操作状态管理
  const actionStates = reactive<ProviderActionState>({
    toggling: new Set(),
    testing: new Set(),
    deleting: new Set(),
  })

  // 统一错误处理器
  const handleError = (error: any, context: string): string => {
    console.error(`${context} Error:`, error)

    if (error?.response?.status === 422) {
      const validationErrors = error.response.data?.errors
      if (validationErrors) {
        return Object.values(validationErrors).flat().join('; ')
      }
    }

    const statusMessages = {
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

  // 复制到剪贴板
  const copyToClipboard = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text)
      msg.success(`${label}已复制到剪贴板`)
      return true
    }
    catch (err) {
      console.error('复制失败:', err)
      msg.error('复制失败，请手动复制')
      return false
    }
  }

  // 防抖的切换状态函数
  const debouncedToggle = debounce(async (
    id: number,
    enabled: boolean,
    onSuccess?: () => void,
  ) => {
    if (actionStates.toggling.has(id)) { return }

    actionStates.toggling.add(id)
    try {
      await toggleProvider(id, enabled)
      msg.success(`${enabled ? '启用' : '禁用'}成功`)
      onSuccess?.()
    }
    catch (error: any) {
      msg.error(handleError(error, '状态切换'))
      onSuccess?.() // 仍然需要刷新以恢复正确状态
    }
    finally {
      actionStates.toggling.delete(id)
    }
  }, 300)

  // 删除操作
  const handleDelete = async (
    id: number | number[],
    onSuccess?: () => void,
    onSelectionClear?: () => void,
  ) => {
    const ids = Array.isArray(id) ? id : [id]
    const isBatch = Array.isArray(id)

    const message = isBatch
      ? `确定要删除选中的 ${ids.length} 个OAuth提供者吗？删除后相关的用户绑定也会被清除。`
      : '确定要删除这个OAuth提供者吗？删除后相关的用户绑定也会被清除。'

    // 检查操作冲突
    const hasActiveOperations = ids.some(itemId =>
      actionStates.testing.has(itemId)
      || actionStates.toggling.has(itemId)
      || actionStates.deleting.has(itemId),
    )

    if (hasActiveOperations) {
      msg.warning('请等待当前操作完成后再执行删除')
      return
    }

    try {
      await ElMessageBox.confirm(message, '确认删除', {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning',
        dangerouslyUseHTMLString: false,
      })

      // 标记删除状态
      ids.forEach(itemId => actionStates.deleting.add(itemId))

      try {
        if (isBatch) {
          await deleteProviders(ids)
        }
        else {
          await deleteProvider(ids[0])
        }

        msg.success('删除成功')
        onSuccess?.()

        if (isBatch) {
          onSelectionClear?.()
        }
      }
      catch (error: any) {
        msg.error(handleError(error, '删除操作'))
      }
      finally {
        ids.forEach(itemId => actionStates.deleting.delete(itemId))
      }
    }
    catch {
      // 用户取消删除
      ids.forEach(itemId => actionStates.deleting.delete(itemId))
    }
  }

  // 测试连接
  const handleTestProvider = async (id: number) => {
    if (actionStates.testing.has(id)) { return }

    actionStates.testing.add(id)

    const loading = ElLoading.service({
      lock: true,
      text: '正在测试连接...',
      background: 'rgba(0, 0, 0, 0.7)',
    })

    try {
      const response = await testProvider(id)

      if (response.data.status === 'success') {
        const authUrl = response.data.auth_url

        msg.success('连接测试成功')

        if (authUrl) {
          try {
            await ElMessageBox.confirm(
              '连接测试成功！是否要打开认证页面进行完整测试？',
              '测试成功',
              {
                confirmButtonText: '打开认证页面',
                cancelButtonText: '不用了',
                type: 'success',
              },
            )
            window.open(authUrl, '_blank')
          }
          catch {
            // 用户选择不打开
          }
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

  // 批量切换状态
  const handleBatchToggle = async (
    providers: OAuthProvider[],
    enabled: boolean,
    onSuccess?: () => void,
  ) => {
    if (providers.length === 0) {
      msg.warning(`请选择要${enabled ? '启用' : '禁用'}的OAuth提供者`)
      return
    }

    const action = enabled ? '启用' : '禁用'

    try {
      await ElMessageBox.confirm(
        `确定要${action}选中的 ${providers.length} 个OAuth提供者吗？`,
        `批量${action}`,
        {
          confirmButtonText: `确定${action}`,
          cancelButtonText: '取消',
          type: 'warning',
        },
      )

      const promises = providers.map(item =>
        debouncedToggle(item.id, enabled, onSuccess),
      )

      await Promise.all(promises)
      msg.success(`批量${action}操作完成`)
    }
    catch {
      // 用户取消操作
    }
  }

  return {
    actionStates,
    copyToClipboard,
    debouncedToggle,
    handleDelete,
    handleTestProvider,
    handleBatchToggle,
    handleError,
  }
}
