<!--
 - OAuth Provider Form Component
 -
 - Handles creation and editing of OAuth providers with dynamic form fields
 - Includes validation, scope management, and provider-specific configurations
 - Follows MineAdmin form patterns and Element Plus components
-->
<script setup lang="tsx">
import type { FormInstance } from 'element-plus'
import type {
  CreateProviderRequest,
  OAuthProvider,
  OAuthProviderName,
  ProviderOption,
  UpdateProviderRequest,
} from '../api/types'

import {
  createProvider,
  getProviderOptions,
  updateProvider,
  validateProviderConfig,
} from '../api/oauthApi'
import { useMessage } from '@/hooks/useMessage.ts'

interface Props {
  formType: 'add' | 'edit'
  data?: OAuthProvider
}

interface Emits {
  (e: 'success'): void
}

const props = withDefaults(defineProps<Props>(), {
  formType: 'add',
  data: undefined,
})

const emit = defineEmits<Emits>()

const msg = useMessage()
const maForm = ref<FormInstance>()
const loading = ref(false)
const providerOptions = ref<ProviderOption[]>(getProviderOptions())

// 表单数据
const formData = ref<CreateProviderRequest>({
  name: 'github',
  display_name: '',
  client_id: '',
  client_secret: '',
  redirect_uri: '',
  scopes: [],
  extra_config: {},
  enabled: true,
  sort: 0,
  remark: '',
})

// 表单验证规则
const formRules = {
  name: [
    { required: true, message: '请选择提供者类型', trigger: 'change' },
  ],
  display_name: [
    { required: true, message: '请输入显示名称', trigger: 'blur' },
    { min: 1, max: 50, message: '显示名称长度在 1 到 50 个字符', trigger: 'blur' },
  ],
  client_id: [
    { required: true, message: '请输入客户端ID', trigger: 'blur' },
    { min: 10, max: 200, message: '客户端ID长度在 10 到 200 个字符', trigger: 'blur' },
  ],
  redirect_uri: [
    { required: true, message: '请输入回调地址', trigger: 'blur' },
    {
      validator: (rule: any, value: string, callback: Function) => {
        if (!value) {
          callback()
          return
        }
        try {
          new URL(value)
          callback()
        }
        catch {
          callback(new Error('请输入有效的URL地址'))
        }
      },
      trigger: 'blur',
    },
  ],
  sort: [
    { type: 'number', min: 0, max: 9999, message: '排序值范围 0-9999', trigger: 'blur' },
  ],
}

// 当前选中的提供者选项
const currentProviderOption = computed(() => {
  return providerOptions.value.find(option => option.value === formData.value.name)
})

// 监听提供者类型变化，更新默认值
watch(() => formData.value.name, (newName: OAuthProviderName) => {
  const option = providerOptions.value.find(opt => opt.value === newName)
  if (option) {
    // 更新显示名称（如果为空）
    if (!formData.value.display_name) {
      formData.value.display_name = option.label
    }

    // 更新默认作用域
    if (!formData.value.scopes || formData.value.scopes.length === 0) {
      formData.value.scopes = [...option.default_scopes]
    }

    // 更新回调地址模板（如果为空）
    if (!formData.value.redirect_uri) {
      formData.value.redirect_uri = `${window.location.origin}/oauth/callback/${newName}`
    }
  }
})

// 作用域管理
const newScope = ref('')

function addScope() {
  if (newScope.value.trim() && !formData.value.scopes?.includes(newScope.value.trim())) {
    if (!formData.value.scopes) {
      formData.value.scopes = []
    }
    formData.value.scopes.push(newScope.value.trim())
    newScope.value = ''
  }
}

function removeScope(index: number) {
  formData.value.scopes?.splice(index, 1)
}

function resetToDefaultScopes() {
  if (currentProviderOption.value) {
    formData.value.scopes = [...currentProviderOption.value.default_scopes]
  }
}

// 额外配置管理
const newConfigKey = ref('')
const newConfigValue = ref('')

function addExtraConfig() {
  if (newConfigKey.value.trim() && newConfigValue.value.trim()) {
    if (!formData.value.extra_config) {
      formData.value.extra_config = {}
    }
    formData.value.extra_config[newConfigKey.value.trim()] = newConfigValue.value.trim()
    newConfigKey.value = ''
    newConfigValue.value = ''
  }
}

function removeExtraConfig(key: string) {
  if (formData.value.extra_config) {
    delete formData.value.extra_config[key]
  }
}

// 初始化表单数据
function initFormData() {
  if (props.formType === 'edit' && props.data) {
    formData.value = {
      name: props.data.name,
      display_name: props.data.display_name,
      client_id: props.data.client_id,
      client_secret: '', // 出于安全考虑，不显示原密钥
      redirect_uri: props.data.redirect_uri,
      scopes: props.data.scopes ? [...props.data.scopes] : [],
      extra_config: props.data.extra_config ? { ...props.data.extra_config } : {},
      enabled: props.data.enabled,
      sort: props.data.sort,
      remark: props.data.remark || '',
    }
  }
  else {
    // 新增模式，重置为默认值
    formData.value = {
      name: 'github',
      display_name: '',
      client_id: '',
      client_secret: '',
      redirect_uri: '',
      scopes: [],
      extra_config: {},
      enabled: true,
      sort: 0,
      remark: '',
    }
  }
}

// 新增
async function add() {
  loading.value = true
  try {
    // 客户端验证
    const errors = validateProviderConfig(formData.value)
    if (errors.length > 0) {
      msg.error(errors.join('; '))
      return { code: 400, message: errors.join('; ') }
    }

    const response = await createProvider(formData.value)
    emit('success')
    return response
  }
  catch (error: any) {
    throw error
  }
  finally {
    loading.value = false
  }
}

// 编辑
async function edit() {
  if (!props.data?.id) {
    throw new Error('缺少提供者ID')
  }

  loading.value = true
  try {
    // 准备更新数据，移除客户端密钥如果为空
    const updateData: UpdateProviderRequest = { ...formData.value }
    if (!updateData.client_secret) {
      delete updateData.client_secret
    }

    const response = await updateProvider(props.data.id, updateData)
    emit('success')
    return response
  }
  catch (error: any) {
    throw error
  }
  finally {
    loading.value = false
  }
}

// 暴露方法
defineExpose({
  maForm,
  add,
  edit,
})

// 组件挂载时初始化
onMounted(() => {
  initFormData()
})

// 监听 props 变化重新初始化
watch(() => [props.formType, props.data], () => {
  initFormData()
}, { deep: true })
</script>

<template>
  <div class="oauth-provider-form">
    <el-form
      ref="maForm"
      v-loading="loading"
      :model="formData"
      :rules="formRules"
      label-width="120px"
    >
      <!-- 基本信息 -->
      <el-card class="mb-4" shadow="never">
        <template #header>
          <div class="flex items-center space-x-2">
            <i class="i-solar:settings-outline" />
            <span class="font-medium">基本配置</span>
          </div>
        </template>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="提供者类型" prop="name">
              <el-select
                v-model="formData.name"
                placeholder="选择OAuth提供者"
                :disabled="formType === 'edit'"
                class="w-full"
              >
                <el-option
                  v-for="option in providerOptions"
                  :key="option.value"
                  :label="option.label"
                  :value="option.value"
                >
                  <div class="flex items-center space-x-2">
                    <div
                      class="h-4 w-4 flex items-center justify-center rounded text-xs text-white"
                      :style="{ backgroundColor: option.brand_color }"
                    >
                      {{ option.label.charAt(0) }}
                    </div>
                    <span>{{ option.label }}</span>
                  </div>
                </el-option>
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="显示名称" prop="display_name">
              <el-input
                v-model="formData.display_name"
                placeholder="自定义显示名称"
                clearable
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="客户端ID" prop="client_id">
              <el-input
                v-model="formData.client_id"
                placeholder="OAuth应用客户端ID"
                clearable
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="客户端密钥" prop="client_secret">
              <el-input
                v-model="formData.client_secret"
                type="password"
                placeholder="OAuth应用客户端密钥"

                clearable show-password
              />
              <div v-if="formType === 'edit'" class="mt-1 text-xs text-gray-500">
                留空表示不更改现有密钥
              </div>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="回调地址" prop="redirect_uri">
          <el-input
            v-model="formData.redirect_uri"
            placeholder="OAuth授权回调地址"
            clearable
          />
          <div class="mt-1 text-xs text-gray-500">
            确保此地址与OAuth应用配置中的回调地址一致
          </div>
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="启用状态">
              <el-switch
                v-model="formData.enabled"
                active-text="启用"
                inactive-text="禁用"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="排序" prop="sort">
              <el-input-number
                v-model="formData.sort"
                :min="0"
                :max="9999"
                placeholder="排序值"
                class="w-full"
              />
            </el-form-item>
          </el-col>
        </el-row>
      </el-card>

      <!-- 授权范围配置 -->
      <el-card class="mb-4" shadow="never">
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <i class="i-solar:shield-check-outline" />
              <span class="font-medium">授权范围</span>
            </div>
            <el-button
              size="small"
              type="info"
              plain
              @click="resetToDefaultScopes"
            >
              重置为默认
            </el-button>
          </div>
        </template>

        <div class="space-y-3">
          <!-- 当前作用域 -->
          <div v-if="formData.scopes && formData.scopes.length > 0" class="flex flex-wrap gap-2">
            <el-tag
              v-for="(scope, index) in formData.scopes"
              :key="scope"
              closable
              type="primary"
              @close="removeScope(index)"
            >
              {{ scope }}
            </el-tag>
          </div>

          <!-- 添加新作用域 -->
          <div class="flex space-x-2">
            <el-input
              v-model="newScope"
              placeholder="输入新的授权范围"
              class="flex-1"
              @keyup.enter="addScope"
            />
            <el-button type="primary" @click="addScope">
              添加
            </el-button>
          </div>

          <!-- 默认作用域提示 -->
          <div v-if="currentProviderOption" class="text-sm text-gray-600">
            <div class="font-medium">
              {{ currentProviderOption.label }} 默认作用域：
            </div>
            <div class="mt-1 flex flex-wrap gap-1">
              <el-tag
                v-for="scope in currentProviderOption.default_scopes"
                :key="scope"
                size="small"
                type="info"
              >
                {{ scope }}
              </el-tag>
            </div>
          </div>
        </div>
      </el-card>

      <!-- 额外配置 -->
      <el-card class="mb-4" shadow="never">
        <template #header>
          <div class="flex items-center space-x-2">
            <i class="i-solar:settings-linear" />
            <span class="font-medium">额外配置</span>
          </div>
        </template>

        <div class="space-y-3">
          <!-- 当前配置 -->
          <div v-if="formData.extra_config && Object.keys(formData.extra_config).length > 0">
            <div class="space-y-2">
              <div
                v-for="[key, value] in Object.entries(formData.extra_config)"
                :key="key"
                class="flex items-center justify-between rounded bg-gray-50 p-2"
              >
                <div class="flex-1">
                  <span class="font-medium">{{ key }}:</span>
                  <span class="ml-2 text-gray-600">{{ value }}</span>
                </div>
                <el-button
                  size="small"
                  type="danger"
                  text
                  @click="removeExtraConfig(key)"
                >
                  删除
                </el-button>
              </div>
            </div>
          </div>

          <!-- 添加新配置 -->
          <div class="flex space-x-2">
            <el-input
              v-model="newConfigKey"
              placeholder="配置键"
              class="flex-1"
            />
            <el-input
              v-model="newConfigValue"
              placeholder="配置值"
              class="flex-1"
            />
            <el-button type="primary" @click="addExtraConfig">
              添加
            </el-button>
          </div>
        </div>
      </el-card>

      <!-- 备注 -->
      <el-card shadow="never">
        <template #header>
          <div class="flex items-center space-x-2">
            <i class="i-solar:document-text-outline" />
            <span class="font-medium">备注信息</span>
          </div>
        </template>

        <el-form-item label="备注" prop="remark">
          <el-input
            v-model="formData.remark"
            type="textarea"
            :rows="3"
            placeholder="可选的备注信息..."
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-card>
    </el-form>
  </div>
</template>

<style scoped lang="scss">
.oauth-provider-form {
  max-width: 800px;
}

.el-card {
  border: 1px solid #e4e7ed;

  :deep(.el-card__header) {
    background: #fafafa;
    border-bottom: 1px solid #e4e7ed;
  }
}
</style>
