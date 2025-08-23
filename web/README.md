# OAuth2 Plugin Frontend Components

OAuth2 第三方登录管理插件的前端组件库，提供完整的 OAuth 绑定、管理和个人中心集成解决方案。

## 📦 组件概览

### 管理端组件 (Admin Components)
- **ProviderForm.vue** - OAuth 提供者配置表单
- **ProviderManagement** - 提供者管理页面 (`views/provider/`)
- **UserBindings** - 用户绑定管理页面 (`views/bindings/`)
- **Statistics** - OAuth 统计分析页面 (`views/statistics/`)

### 用户端组件 (User Components)
- **PersonalOAuthBindings** - 个人中心 OAuth 绑定管理
- **ProviderButton** - 可复用的 OAuth 提供者按钮
- **BindingCard** - OAuth 绑定信息卡片
- **StatusIndicator** - OAuth 连接状态指示器
- **OAuthFlowHandler** - OAuth 授权流程处理器

## 🚀 快速开始

### 1. 基本使用

```vue
<template>
  <!-- 个人中心 OAuth 绑定 -->
  <PersonalOAuthBindings />
  
  <!-- 单独的绑定按钮 -->
  <ProviderButton 
    provider="github" 
    action="bind"
    @click="handleBind"
  />
  
  <!-- 绑定状态指示器 -->
  <StatusIndicator 
    status="connected"
    provider="dingtalk"
    :show-text="true"
  />
</template>

<script setup lang="ts">
import { 
  PersonalOAuthBindings,
  ProviderButton, 
  StatusIndicator 
} from '@/plugins/maimaitech/oauth2'

const handleBind = (provider: string) => {
  // 处理绑定逻辑
}
</script>
```

### 2. API 使用

```typescript
import {
  getCurrentUserBindings,
  getAvailableProviders,
  bindAccount,
  unbindAccount,
  refreshToken
} from '@/plugins/maimaitech/oauth2'

// 获取当前用户的 OAuth 绑定
const bindings = await getCurrentUserBindings()

// 获取可用的 OAuth 提供者
const providers = await getAvailableProviders()

// 绑定账号
await bindAccount('github', authCode)

// 解绑账号
await unbindAccount('github')

// 刷新令牌
await refreshToken('dingtalk')
```

## 📋 组件详细文档

### PersonalOAuthBindings

个人中心 OAuth 绑定管理主组件，提供完整的绑定管理界面。

**特性:**
- 显示已绑定的第三方账号
- 支持绑定新的第三方账号
- 提供解绑和刷新令牌功能
- 响应式设计，支持移动端
- 完整的错误处理和加载状态

**使用:**
```vue
<PersonalOAuthBindings />
```

### ProviderButton

可复用的 OAuth 提供者按钮组件。

**Props:**
- `provider` - OAuth 提供者名称
- `variant` - 按钮样式 ('filled' | 'outlined' | 'text')  
- `size` - 按钮大小 ('large' | 'default' | 'small')
- `action` - 操作类型 ('bind' | 'unbind' | 'login' | 'connect')
- `loading` - 加载状态
- `disabled` - 禁用状态
- `iconOnly` - 仅显示图标
- `width` - 自定义宽度

**Events:**
- `click` - 点击事件，参数为 provider 名称

**使用示例:**
```vue
<ProviderButton 
  provider="github"
  variant="filled"
  action="bind"
  :loading="false"
  @click="handleProviderClick"
/>
```

### BindingCard

OAuth 绑定信息展示卡片组件。

**Props:**
- `binding` - 绑定数据对象
- `loading` - 加载状态
- `errorMessage` - 错误消息

**Events:**
- `refresh` - 刷新令牌事件
- `unbind` - 解绑事件
- `updated` - 数据更新事件

**使用示例:**
```vue
<BindingCard 
  :binding="bindingData"
  :loading="false"
  @refresh="handleRefresh"
  @unbind="handleUnbind"
/>
```

### StatusIndicator

OAuth 连接状态指示器组件。

**Props:**
- `status` - 连接状态 ('connected' | 'disconnected' | 'expired' | 'error' | 'pending' | 'disabled')
- `provider` - OAuth 提供者名称
- `lastSync` - 最后同步时间
- `errorMessage` - 错误消息
- `showText` - 显示状态文本
- `iconSize` - 图标大小
- `showTooltip` - 显示工具提示

**使用示例:**
```vue
<StatusIndicator 
  status="connected"
  provider="dingtalk"
  :show-text="true"
  :icon-size="16"
/>
```

### OAuthFlowHandler

OAuth 授权流程处理组件，用于处理 OAuth 回调和授权流程。

**Props:**
- `autoStart` - 自动开始处理
- `successRedirect` - 成功后重定向地址
- `errorRedirect` - 错误后重定向地址

**Events:**
- `success` - 授权成功事件
- `error` - 授权失败事件
- `complete` - 流程完成事件

**使用示例:**
```vue
<OAuthFlowHandler 
  :auto-start="true"
  success-redirect="/personal/bindings"
  @success="handleSuccess"
  @error="handleError"
/>
```

## 🎨 样式定制

所有组件都支持 CSS 变量定制和深度样式覆盖：

```scss
// 自定义 OAuth 按钮颜色
.oauth-provider-button {
  --provider-color: #your-color;
  --provider-hover-color: #your-hover-color;
}

// 自定义状态指示器
.oauth-status-indicator {
  --status-connected-color: #67c23a;
  --status-error-color: #f56c6c;
}

// 自定义绑定卡片
.oauth-binding-card {
  --card-border-radius: 12px;
  --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
```

## 🔧 配置选项

### 支持的 OAuth 提供者

```typescript
const SUPPORTED_PROVIDERS = {
  dingtalk: { name: '钉钉', color: '#0089ff' },
  github: { name: 'GitHub', color: '#333' },
  gitee: { name: 'Gitee', color: '#c71c27' },
  feishu: { name: '飞书', color: '#00d4aa' },
  wechat: { name: '微信', color: '#07c160' },
  qq: { name: 'QQ', color: '#12b7f5' }
}
```

### API 端点配置

```typescript
// 用户端 API (无需管理员权限)
const USER_ENDPOINTS = {
  bindings: '/oauth/bindings',           // 获取用户绑定
  providers: '/oauth/providers',         // 获取可用提供者
  authorize: '/oauth/authorize/{provider}', // 开始授权
  callback: '/oauth/callback/{provider}',   // 处理回调
  bind: '/oauth/bind/{provider}',        // 绑定账号
  unbind: '/oauth/unbind/{provider}',    // 解绑账号
  refresh: '/oauth/refresh/{provider}'   // 刷新令牌
}

// 管理端 API (需要管理员权限)
const ADMIN_ENDPOINTS = {
  providers: '/admin/oauth/providers',   // 提供者管理
  bindings: '/admin/oauth/bindings',     // 绑定管理
  statistics: '/admin/oauth/statistics'  // 统计数据
}
```

## 🌐 国际化支持

组件支持多语言，当前支持简体中文，可扩展其他语言：

```typescript
const i18nMessages = {
  'zh-CN': {
    oauth2: {
      bind: '绑定',
      unbind: '解绑',
      connected: '已连接',
      disconnected: '未连接',
      expired: '已过期',
      error: '错误',
      // ... 更多翻译
    }
  }
}
```

## 📱 移动端适配

所有组件都经过移动端优化：
- 响应式布局设计
- 触摸友好的交互
- 移动端菜单适配
- 小屏幕优化显示

## 🔐 安全特性

- CSRF 防护 (State 参数验证)
- XSS 防护 (输入验证和转义)
- 令牌安全存储
- 授权状态验证
- 安全的重定向处理

## 🎯 最佳实践

1. **组件使用:**
   - 在个人中心页面使用 `PersonalOAuthBindings`
   - 在登录页面使用 `ProviderButton`
   - 在状态展示时使用 `StatusIndicator`

2. **API 调用:**
   - 始终处理 API 错误
   - 使用 loading 状态提升用户体验
   - 实现适当的错误重试机制

3. **用户体验:**
   - 提供清晰的操作反馈
   - 使用合适的加载动画
   - 实现无障碍访问支持

## 🐛 故障排除

### 常见问题

1. **组件不显示**
   - 检查 OAuth 提供者配置
   - 验证 API 端点可访问性
   - 检查用户权限设置

2. **授权失败**
   - 验证 OAuth 应用配置
   - 检查回调 URL 设置
   - 确认网络连接正常

3. **样式问题**
   - 检查 CSS 变量定义
   - 验证主题配置
   - 确认组件导入正确

## 📄 许可证

本插件遵循 MIT 许可证，详见 LICENSE 文件。

---

**作者:** MaimaiTech  
**版本:** 1.0.0  
**更新:** 2024-08-22