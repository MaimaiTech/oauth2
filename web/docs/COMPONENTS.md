# OAuth2 Plugin Components Documentation

This document provides comprehensive documentation for all OAuth2 plugin components, their usage, props, events, and integration examples.

## Table of Contents

1. [OAuth Components](#oauth-components)
2. [User Components](#user-components)
3. [Integration Examples](#integration-examples)
4. [API Reference](#api-reference)
5. [TypeScript Interfaces](#typescript-interfaces)

## OAuth Components

### ProviderButton

A versatile button component for OAuth provider interactions with dynamic styling, icon support, and multiple action types.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `provider` | `OAuthProviderName` | - | **Required.** OAuth provider name (dingtalk, github, gitee, feishu, wechat, qq) |
| `variant` | `'filled' \| 'outlined' \| 'text'` | `'filled'` | Button style variant |
| `size` | `'large' \| 'default' \| 'small'` | `'default'` | Button size |
| `loading` | `boolean` | `false` | Loading state |
| `disabled` | `boolean` | `false` | Disabled state |
| `text` | `string` | - | Custom button text (overrides default) |
| `iconOnly` | `boolean` | `false` | Show icon only (no text) |
| `width` | `string` | - | Custom width |
| `action` | `'bind' \| 'unbind' \| 'login' \| 'connect'` | `'bind'` | Action type for different contexts |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `click` | `provider: OAuthProviderName` | Emitted when button is clicked |

#### Usage Examples

```vue
<!-- Basic binding button -->
<ProviderButton 
  provider="github" 
  @click="handleProviderClick" 
/>

<!-- Outlined variant for unbinding -->
<ProviderButton
  provider="dingtalk"
  variant="outlined"
  action="unbind"
  @click="handleUnbind"
/>

<!-- Custom styling -->
<ProviderButton
  provider="wechat"
  size="large"
  width="200px"
  text="登录微信"
  @click="handleLogin"
/>

<!-- Icon only button -->
<ProviderButton
  provider="github"
  icon-only
  variant="text"
  @click="handleConnect"
/>

<!-- Loading state -->
<ProviderButton
  provider="feishu"
  :loading="isBinding"
  action="connect"
  @click="handleConnect"
/>
```

### BindingCard

A card component that displays OAuth account binding information with status indicators and action buttons.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `binding` | `UserOAuthBinding` | - | **Required.** User OAuth binding data |
| `showActions` | `boolean` | `true` | Show action buttons |
| `compact` | `boolean` | `false` | Compact layout mode |
| `showAvatar` | `boolean` | `true` | Show provider avatar/icon |
| `showLastLogin` | `boolean` | `true` | Show last login information |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `unbind` | `binding: UserOAuthBinding` | Emitted when unbind is requested |
| `refresh` | `binding: UserOAuthBinding` | Emitted when token refresh is requested |
| `view-details` | `binding: UserOAuthBinding` | Emitted when view details is clicked |

#### Usage Examples

```vue
<!-- Full binding card -->
<BindingCard
  :binding="userBinding"
  @unbind="handleUnbind"
  @refresh="handleRefresh"
  @view-details="showDetails"
/>

<!-- Compact mode for lists -->
<BindingCard
  v-for="binding in bindings"
  :key="binding.id"
  :binding="binding"
  compact
  :show-avatar="false"
  @unbind="confirmUnbind"
/>

<!-- Read-only mode -->
<BindingCard
  :binding="binding"
  :show-actions="false"
  :show-last-login="false"
/>
```

### StatusIndicator

A small indicator component showing OAuth binding status with color coding and tooltip support.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `status` | `OAuthStatus` | - | **Required.** OAuth status (normal, disabled, pending) |
| `size` | `'small' \| 'medium' \| 'large'` | `'medium'` | Indicator size |
| `showText` | `boolean` | `false` | Show status text |
| `tooltip` | `string` | - | Custom tooltip text |

#### Usage Examples

```vue
<!-- Basic status indicator -->
<StatusIndicator :status="binding.status" />

<!-- With text -->
<StatusIndicator 
  :status="binding.status" 
  show-text 
  size="large" 
/>

<!-- Custom tooltip -->
<StatusIndicator
  :status="binding.status"
  :tooltip="`Last updated: ${binding.updated_at}`"
/>
```

### OAuthFlowHandler

A comprehensive component that handles the complete OAuth authorization flow including redirects, state management, and error handling.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `provider` | `OAuthProviderName` | - | **Required.** OAuth provider name |
| `autoStart` | `boolean` | `true` | Automatically start OAuth flow on mount |
| `redirectUrl` | `string` | - | Custom redirect URL after completion |
| `showProgress` | `boolean` | `true` | Show progress indicators |
| `timeout` | `number` | `300000` | Timeout in milliseconds (5 minutes) |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `start` | `provider: OAuthProviderName` | Emitted when OAuth flow starts |
| `success` | `binding: UserOAuthBinding` | Emitted when binding succeeds |
| `error` | `error: Error` | Emitted when an error occurs |
| `timeout` | `provider: OAuthProviderName` | Emitted when flow times out |
| `cancel` | `provider: OAuthProviderName` | Emitted when user cancels |

#### Usage Examples

```vue
<!-- Auto-start OAuth flow -->
<OAuthFlowHandler
  provider="github"
  @success="handleSuccess"
  @error="handleError"
/>

<!-- Manual control -->
<OAuthFlowHandler
  provider="dingtalk"
  :auto-start="false"
  ref="oauthHandler"
  @success="onBindingComplete"
/>

<script setup>
const oauthHandler = ref()

const startOAuth = () => {
  oauthHandler.value.start()
}

const cancelOAuth = () => {
  oauthHandler.value.cancel()
}
</script>
```

## User Components

### PersonalOAuthBindings

A complete personal center component for managing user's OAuth bindings with provider listing, binding/unbinding actions, and status management.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `userId` | `number` | - | User ID (defaults to current user) |
| `layout` | `'grid' \| 'list'` | `'grid'` | Display layout mode |
| `showHeader` | `boolean` | `true` | Show component header |
| `allowBind` | `boolean` | `true` | Allow new bindings |
| `allowUnbind` | `boolean` | `true` | Allow unbinding accounts |

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `bind-success` | `{ provider: OAuthProviderName, binding: UserOAuthBinding }` | Emitted when binding succeeds |
| `unbind-success` | `{ provider: OAuthProviderName }` | Emitted when unbinding succeeds |
| `error` | `error: Error` | Emitted when an operation fails |

#### Usage Examples

```vue
<!-- Full personal bindings component -->
<PersonalOAuthBindings
  @bind-success="handleBindSuccess"
  @unbind-success="handleUnbindSuccess"
  @error="handleError"
/>

<!-- List layout for mobile -->
<PersonalOAuthBindings
  layout="list"
  :show-header="false"
/>

<!-- Read-only mode for admin view -->
<PersonalOAuthBindings
  :user-id="selectedUser.id"
  :allow-bind="false"
  :allow-unbind="false"
/>
```

## Integration Examples

### Basic Personal Center Integration

```vue
<template>
  <div class="personal-oauth-section">
    <h3>{{ $t('oauth2.personal.title') }}</h3>
    <p class="subtitle">{{ $t('oauth2.personal.subtitle') }}</p>
    
    <PersonalOAuthBindings
      @bind-success="onBindSuccess"
      @unbind-success="onUnbindSuccess"
      @error="onError"
    />
  </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { PersonalOAuthBindings } from '@/plugins/maimaitech/oauth2'

const onBindSuccess = ({ provider, binding }) => {
  ElMessage.success(`Successfully bound ${provider} account`)
  // Refresh user data or perform other actions
}

const onUnbindSuccess = ({ provider }) => {
  ElMessage.success(`Successfully unbound ${provider} account`)
}

const onError = (error) => {
  ElMessage.error(error.message || 'An error occurred')
}
</script>
```

### Custom Provider Selection

```vue
<template>
  <div class="provider-selection">
    <h4>Connect Your Accounts</h4>
    <div class="provider-grid">
      <ProviderButton
        v-for="provider in availableProviders"
        :key="provider.name"
        :provider="provider.name"
        :disabled="provider.is_bound"
        @click="handleProviderSelect"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ProviderButton } from '@/plugins/maimaitech/oauth2'
import { getAvailableProviders } from '@/plugins/maimaitech/oauth2/api/userOAuthApi'

const availableProviders = ref([])

const handleProviderSelect = (provider) => {
  // Handle provider selection
  console.log('Selected provider:', provider)
}

onMounted(async () => {
  try {
    const response = await getAvailableProviders()
    availableProviders.value = response.data
  } catch (error) {
    console.error('Failed to load providers:', error)
  }
})
</script>

<style scoped>
.provider-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
  margin-top: 16px;
}
</style>
```

### Admin Panel Integration

```vue
<template>
  <div class="admin-oauth-management">
    <!-- Provider Management -->
    <el-card>
      <template #header>
        <h3>OAuth Provider Management</h3>
      </template>
      
      <el-table :data="providers">
        <el-table-column prop="display_name" label="Provider" />
        <el-table-column prop="status" label="Status">
          <template #default="{ row }">
            <StatusIndicator :status="row.status" show-text />
          </template>
        </el-table-column>
        <el-table-column label="Actions">
          <template #default="{ row }">
            <el-button @click="editProvider(row)">Edit</el-button>
            <el-button 
              type="danger" 
              @click="deleteProvider(row)"
            >
              Delete
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- User Bindings Overview -->
    <el-card class="mt-4">
      <template #header>
        <h3>User Bindings</h3>
      </template>
      
      <div class="bindings-grid">
        <BindingCard
          v-for="binding in userBindings"
          :key="binding.id"
          :binding="binding"
          compact
          @unbind="handleForceUnbind"
        />
      </div>
    </el-card>
  </div>
</template>
```

## API Reference

### Main API Functions

```typescript
// User OAuth APIs
import {
  getCurrentUserBindings,
  getAvailableProviders,
  authorizeProvider,
  bindAccount,
  unbindAccount,
  processOAuthCallback,
  refreshToken,
  checkBindingStatus
} from '@/plugins/maimaitech/oauth2/api/userOAuthApi'

// Admin OAuth APIs
import {
  getProviders,
  createProvider,
  updateProvider,
  deleteProvider,
  toggleProvider,
  testConnection,
  getUserBindings,
  forceUnbind,
  getStatistics
} from '@/plugins/maimaitech/oauth2/api/oauthApi'
```

### Utility Functions

```typescript
// Provider utilities
import {
  getOAuthProviders,
  getProviderConfig,
  getProviderColor,
  getProviderIcon,
  getProviderName,
  supportsRefreshToken
} from '@/plugins/maimaitech/oauth2/api/userOAuthApi'

// OAuth state management
import {
  generateOAuthState,
  validateOAuthState,
  storeOAuthState,
  retrieveOAuthState,
  clearOAuthState
} from '@/plugins/maimaitech/oauth2/api/userOAuthApi'
```

## TypeScript Interfaces

### Core Types

```typescript
type OAuthProviderName = 'dingtalk' | 'github' | 'gitee' | 'feishu' | 'wechat' | 'qq'
type OAuthStatus = 'normal' | 'disabled' | 'pending'

interface OAuthProvider {
  id: number
  name: OAuthProviderName
  display_name: string
  client_id: string
  client_secret?: string
  redirect_uri: string
  scopes?: string[]
  enabled: boolean
  status: OAuthStatus
  // ... other fields
}

interface UserOAuthAccount {
  id: number
  user_id: number
  provider: OAuthProviderName
  provider_user_id: string
  provider_username: string
  provider_nickname?: string
  status: OAuthStatus
  // ... other fields
}
```

### Component Props Types

```typescript
interface ProviderButtonProps {
  provider: OAuthProviderName
  variant?: 'filled' | 'outlined' | 'text'
  size?: 'large' | 'default' | 'small'
  loading?: boolean
  disabled?: boolean
  text?: string
  iconOnly?: boolean
  width?: string
  action?: 'bind' | 'unbind' | 'login' | 'connect'
}

interface BindingCardProps {
  binding: UserOAuthBinding
  showActions?: boolean
  compact?: boolean
  showAvatar?: boolean
  showLastLogin?: boolean
}
```

## Best Practices

### 1. Error Handling

Always implement proper error handling for OAuth operations:

```typescript
const handleOAuthAction = async (action: () => Promise<any>) => {
  try {
    await action()
  } catch (error) {
    if (error.response?.status === 401) {
      // Handle authentication errors
      ElMessage.error('Authentication required')
    } else if (error.response?.status === 403) {
      // Handle authorization errors
      ElMessage.error('Access denied')
    } else {
      // Handle other errors
      ElMessage.error(error.message || 'An error occurred')
    }
  }
}
```

### 2. State Management

Use proper state management for OAuth flows:

```typescript
const oauthState = reactive({
  isBinding: false,
  currentProvider: null,
  bindings: [],
  error: null
})
```

### 3. Security Considerations

- Always validate OAuth state parameters
- Use secure redirect URIs (HTTPS in production)
- Clear OAuth states after use
- Implement proper CSRF protection

### 4. Accessibility

- Provide proper ARIA labels for buttons
- Include keyboard navigation support
- Use semantic HTML elements
- Provide screen reader friendly status indicators

### 5. Mobile Responsiveness

- Use responsive grid layouts
- Implement touch-friendly button sizes
- Provide appropriate spacing on mobile devices
- Consider stacked layouts for small screens

## Troubleshooting

### Common Issues

1. **OAuth callback not working**
   - Verify redirect URI matches exactly
   - Check OAuth state validation
   - Ensure proper route configuration

2. **Provider buttons not showing**
   - Check provider configuration
   - Verify API endpoints are accessible
   - Confirm proper component imports

3. **Translation keys missing**
   - Verify locale files are properly loaded
   - Check translation key paths
   - Ensure i18n is properly configured

4. **Styling issues**
   - Check CSS variable definitions
   - Verify Element Plus theme integration
   - Confirm responsive breakpoints

For additional support, please refer to the MineAdmin documentation or create an issue in the project repository.