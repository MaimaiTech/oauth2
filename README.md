# OAuth2 Third-party Login Plugin

A comprehensive OAuth2 plugin for MineAdmin that supports multiple third-party platform authentication, login, and account binding functionality.

## Features

- 🔐 **Multi-platform Support**: DingTalk, GitHub, Gitee, Feishu, WeChat, QQ
- 🚀 **OAuth2 Login**: Direct login through third-party platforms
- 🎨 **Login Components**: Ready-to-use Vue components for OAuth login
- 👥 **Account Binding**: Users can bind/unbind multiple OAuth2 accounts
- 🛡️ **Security First**: CSRF protection, token encryption, rate limiting
- ⚙️ **Admin Management**: Configure OAuth2 providers through admin panel
- 📱 **Personal Center**: Manage bound accounts in user center
- 🔄 **Token Refresh**: Automatic token refresh where supported
- 📱 **Responsive UI**: Mobile-friendly login components with accessibility support

## Installation

1. Place this plugin in `api/plugin/maimaitech/oauth2/`
2. The plugin will be automatically discovered by MineAdmin
3. Install through the admin panel or run installation script

## Plugin Structure

```
oauth2/
├── mine.json              # Plugin configuration
├── src/
│   ├── ConfigProvider.php # Service container configuration
│   ├── InstallScript.php  # Installation logic with menu setup
│   ├── UninstallScript.php # Cleanup logic
│   ├── Controller/        # API controllers
│   │   ├── Admin/         # Admin panel controllers
│   │   ├── User/          # User binding controllers  
│   │   └── LoginController.php # Login flow controller
│   ├── Service/           # Business logic layer
│   ├── Repository/        # Data access layer
│   ├── Model/             # Eloquent models
│   ├── Request/           # Input validation
│   ├── Schema/            # Response schemas
│   ├── Exception/         # Custom exceptions
│   └── Client/            # OAuth platform clients
├── Database/
│   └── Migrations/        # Database migrations
└── web/                   # Frontend components
    ├── api/               # API client functions
    │   ├── types.ts       # TypeScript type definitions
    │   ├── loginApi.ts    # Login API methods
    │   ├── userOAuthApi.ts # User binding API methods
    │   └── oauthApi.ts    # Admin API methods
    ├── components/        # Vue components
    │   └── oauth/         # OAuth UI components
    │       ├── OAuthLoginButtons.vue # Login button component
    │       ├── ProviderIcon.vue      # Provider icon component
    │       ├── BindingCard.vue       # Account binding card
    │       └── index.ts              # Component exports
    └── views/             # Page components
        ├── callback/      # OAuth callback pages
        │   ├── index.vue  # Account binding callback
        │   └── login_callback.vue # Login callback
        ├── bindings/      # Account management pages
        ├── provider/      # Provider configuration pages
        └── statistics/    # OAuth statistics pages
```

## Menu Structure

After installation, the following menus will be available in the admin panel:

- **OAuth2管理** (system:oauth2)
  - **OAuth2服务商配置** (system:oauth2:providers)
    - View provider list
    - Create/update provider configuration
    - Enable/disable providers
    - Delete provider configuration
  - **用户绑定管理** (system:oauth2:bindings)
    - View user OAuth2 bindings
    - Force unbind user accounts
    - View binding details

## API Endpoints

### Login Endpoints (Public)
- `GET /passport/oauth/login/providers` - Get available OAuth login providers
- `GET /passport/oauth/{provider}` - Initiate OAuth login flow
- `GET /passport/oauth/login/callback/{provider}` - Handle OAuth login callback

### Admin Endpoints
- `GET /admin/oauth2/providers` - List OAuth2 providers
- `POST /admin/oauth2/providers` - Create OAuth2 provider
- `PUT /admin/oauth2/providers/{id}` - Update OAuth2 provider
- `DELETE /admin/oauth2/providers/{id}` - Delete OAuth2 provider
- `POST /admin/oauth2/providers/{id}/toggle` - Enable/disable provider
- `GET /admin/oauth2/bindings` - List user OAuth2 bindings
- `DELETE /admin/oauth2/bindings/{id}` - Force unbind user account

### User Endpoints (Authenticated)
- `GET /oauth2/authorize/{provider}` - Start OAuth2 binding flow
- `GET /oauth2/callback/{provider}` - Handle OAuth2 binding callback
- `POST /oauth2/bind/{provider}` - Bind OAuth2 account
- `DELETE /oauth2/unbind/{provider}` - Unbind OAuth2 account
- `GET /oauth2/bindings` - Get current user's bindings

## Component Usage

### OAuth Login Buttons

The plugin provides ready-to-use Vue components for implementing OAuth login functionality:

```vue
<template>
  <div class="login-page">
    <!-- OAuth Login Buttons -->
    <OAuthLoginButtons 
      layout="horizontal"
      size="large"
      :redirect-uri="'/dashboard'"
      @provider-click="handleProviderClick"
      @error="handleLoginError"
    />
  </div>
</template>

<script setup>
import { OAuthLoginButtons } from '$/maimaitech/oauth2/components/oauth'

const handleProviderClick = (provider) => {
  console.log('用户选择了:', provider)
}

const handleLoginError = (error) => {
  console.error('登录错误:', error)
}
</script>
```

### Component Props

#### OAuthLoginButtons Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `layout` | `'horizontal' \| 'vertical'` | `'horizontal'` | 按钮布局方式 |
| `size` | `'large' \| 'default' \| 'small'` | `'default'` | 按钮大小 |
| `circle` | `boolean` | `false` | 是否显示为圆形按钮 |
| `iconOnly` | `boolean` | `false` | 是否只显示图标 |
| `redirectUri` | `string` | - | 登录成功后的重定向URL |
| `maxProviders` | `number` | `6` | 最大显示的提供商数量 |
| `customClass` | `string` | `''` | 自定义样式类名 |

#### Events

| Event | Parameters | Description |
|-------|------------|-------------|
| `provider-click` | `provider: OAuthProviderName` | 用户点击提供商按钮时触发 |
| `loading-change` | `loading: boolean` | 加载状态变化时触发 |
| `error` | `error: string` | 发生错误时触发 |

### Login Callback Route

Configure the login callback route in your router:

```typescript
{
  path: '/oauth/login/callback/:provider',
  component: () => import('$/maimaitech/oauth2/views/callback/login_callback.vue'),
  name: 'OAuthLoginCallback'
}
```

## Supported Platforms

| Platform | Status | Documentation |
|----------|---------|--------------|
| DingTalk | ✅ Ready | https://dingtalk.apifox.cn/llms.txt |
| GitHub | ✅ Ready | https://docs.github.com/apps/oauth-apps |
| Gitee | ✅ Ready | https://gitee.com/api/v5/oauth_doc |
| Feishu | ✅ Ready | https://open.feishu.cn/document/sso/web-application-sso |
| WeChat | ✅ Ready | WeChat Open Platform |
| QQ | ✅ Ready | QQ Connect |

## Security Features

- **CSRF Protection**: State parameter validation
- **Token Encryption**: Secure storage of access/refresh tokens
- **Rate Limiting**: Prevents OAuth flow abuse
- **Secure Callbacks**: Validates callback parameters
- **Session Management**: Proper session handling

## Configuration

OAuth2 providers are configured through the database (no config files required):

1. Access admin panel → OAuth2管理 → OAuth2服务商配置
2. Create new provider with:
   - Provider name (dingtalk, github, etc.)
   - Display name
   - Client ID
   - Client Secret
   - Redirect URI
   - OAuth scopes (optional)
   - Platform-specific configuration

## Development Status

✅ **Current Implementation Complete**:

- ✅ **Plugin Architecture**: Full MineAdmin plugin structure
- ✅ **Database Schema**: OAuth providers, user bindings, and state management
- ✅ **Service Layer**: Business logic for OAuth flows and token management
- ✅ **API Controllers**: Complete REST APIs for admin, user, and login endpoints
- ✅ **Frontend Components**: Vue 3 + Element Plus UI components
- ✅ **Login System**: Direct OAuth login with JWT token management
- ✅ **Security Features**: CSRF protection, token encryption, secure callbacks
- ✅ **Admin Interface**: Provider configuration and user binding management
- ✅ **User Interface**: Personal OAuth account management

🚀 **Ready for Production Use**

## Latest Updates

### v1.1.0 - OAuth Login System

**New Features:**
- 🚀 **OAuth Login Flow**: Complete authentication system via third-party providers
- 🎨 **Login Components**: `OAuthLoginButtons` and `ProviderIcon` Vue components
- 📱 **Responsive UI**: Mobile-friendly design with accessibility features
- 🔄 **Login Callback**: Dedicated `login_callback.vue` page with progress tracking
- 🛡️ **Enhanced Security**: JWT token management and secure storage
- 📚 **TypeScript Support**: Complete type definitions for all APIs

**API Updates:**
- Added login-specific API endpoints under `/passport/oauth/login/`
- New `loginApi.ts` module for authentication flows
- Enhanced error handling and user feedback
- Automatic token refresh and session management

**Components:**
- `OAuthLoginButtons`: Configurable OAuth provider buttons
- `ProviderIcon`: SVG icons for all supported platforms
- `login_callback.vue`: Complete callback handling with animations

## Best Practices

### Security Recommendations

1. **HTTPS Only**: Always use HTTPS in production environments
2. **State Validation**: Never skip CSRF state parameter validation
3. **Token Storage**: Use secure storage methods for sensitive tokens
4. **Scope Minimization**: Request only necessary OAuth scopes
5. **Regular Updates**: Keep OAuth provider configurations up-to-date

### Performance Tips

1. **Component Lazy Loading**: Use dynamic imports for large OAuth components
2. **Provider Caching**: Cache provider configurations to reduce API calls
3. **Token Refresh**: Implement automatic token refresh for better UX
4. **Error Boundaries**: Use Vue error boundaries around OAuth components

### Integration Examples

#### Custom Login Page

```vue
<template>
  <div class="custom-login">
    <h1>Welcome to MyApp</h1>
    
    <!-- Traditional Login Form -->
    <el-form v-model="loginForm">
      <!-- ... traditional form fields -->
    </el-form>
    
    <el-divider>或使用第三方账号登录</el-divider>
    
    <!-- OAuth Login Buttons -->
    <OAuthLoginButtons 
      layout="horizontal"
      size="large"
      :redirect-uri="$route.query.redirect || '/dashboard'"
      custom-class="my-oauth-buttons"
    />
  </div>
</template>
```

#### Mobile-First Design

```vue
<template>
  <div class="mobile-login">
    <!-- Responsive OAuth buttons -->
    <OAuthLoginButtons 
      layout="vertical"
      size="large"
      :max-providers="4"
      custom-class="mobile-oauth"
    />
  </div>
</template>

<style>
.mobile-oauth {
  padding: 16px;
}

@media (max-width: 768px) {
  .mobile-oauth :deep(.oauth-provider-button) {
    min-height: 56px; /* Better touch targets */
    font-size: 16px;
  }
}
</style>
```

## Troubleshooting

### Common Issues

#### 1. "Provider not configured" Error

**Cause**: OAuth provider not properly configured in admin panel
**Solution**: 
1. Go to admin panel → OAuth2管理 → OAuth2服务商配置
2. Ensure provider is enabled with valid client_id and client_secret
3. Verify redirect_uri matches your application's callback URL

#### 2. CSRF State Mismatch

**Cause**: State parameter validation failed (security feature)
**Solution**:
1. Clear browser cache and cookies
2. Ensure cookies are enabled
3. Check if using HTTPS in production

#### 3. Component Not Found

**Cause**: Incorrect import path or plugin not properly installed
**Solution**:
```typescript
// ✅ Correct import
import { OAuthLoginButtons } from '$/maimaitech/oauth2/components/oauth'

// ❌ Incorrect import
import { OAuthLoginButtons } from './components/oauth'
```

#### 4. Login Callback Timeout

**Cause**: Network issues or slow provider response
**Solution**:
1. Check network connectivity
2. Verify provider's service status
3. Increase timeout in callback component if needed

#### 5. Mobile Responsiveness Issues

**Cause**: CSS viewport or touch targets not optimized
**Solution**:
1. Add viewport meta tag: `<meta name="viewport" content="width=device-width, initial-scale=1">`
2. Use `layout="vertical"` for mobile layouts
3. Test touch interactions on actual devices

### Debug Mode

Enable debug logging in development:

```typescript
// In your component
const handleLoginError = (error) => {
  if (import.meta.env.DEV) {
    console.group('OAuth Login Error')
    console.error('Error:', error)
    console.log('Current URL:', window.location.href)
    console.log('User Agent:', navigator.userAgent)
    console.groupEnd()
  }
}
```

### Performance Monitoring

Track OAuth login performance:

```typescript
const handleProviderClick = async (provider) => {
  const startTime = performance.now()
  
  try {
    // ... login logic
    
    const endTime = performance.now()
    console.log(`OAuth ${provider} login took ${endTime - startTime} ms`)
  } catch (error) {
    // ... error handling
  }
}
```

## Version

Current version: **1.1.0** (Production Ready with Login System)

## License

This plugin follows the same license as MineAdmin.