# OAuth2 Third-party Login Plugin

A comprehensive OAuth2 plugin for MineAdmin that supports multiple third-party platform authentication and account binding functionality.

## Features

- 🔐 **Multi-platform Support**: DingTalk, GitHub, Gitee, Feishu, WeChat, QQ
- 👥 **Account Binding**: Users can bind/unbind multiple OAuth2 accounts
- 🛡️ **Security First**: CSRF protection, token encryption, rate limiting
- ⚙️ **Admin Management**: Configure OAuth2 providers through admin panel
- 📱 **Personal Center**: Manage bound accounts in user center
- 🔄 **Token Refresh**: Automatic token refresh where supported

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

### Admin Endpoints
- `GET /admin/oauth2/providers` - List OAuth2 providers
- `POST /admin/oauth2/providers` - Create OAuth2 provider
- `PUT /admin/oauth2/providers/{id}` - Update OAuth2 provider
- `DELETE /admin/oauth2/providers/{id}` - Delete OAuth2 provider
- `POST /admin/oauth2/providers/{id}/toggle` - Enable/disable provider
- `GET /admin/oauth2/bindings` - List user OAuth2 bindings
- `DELETE /admin/oauth2/bindings/{id}` - Force unbind user account

### User Endpoints
- `GET /oauth2/authorize/{provider}` - Start OAuth2 flow
- `GET /oauth2/callback/{provider}` - Handle OAuth2 callback
- `POST /oauth2/bind/{provider}` - Bind OAuth2 account (auth required)
- `DELETE /oauth2/unbind/{provider}` - Unbind OAuth2 account (auth required)
- `GET /oauth2/bindings` - Get current user's bindings (auth required)

## Supported Platforms

| Platform | Status | Documentation |
|----------|---------|--------------|
| DingTalk | ✅ Planned | https://dingtalk.apifox.cn/llms.txt |
| GitHub | ✅ Planned | https://docs.github.com/apps/oauth-apps |
| Gitee | ✅ Planned | https://gitee.com/api/v5/oauth_doc |
| Feishu | ✅ Planned | https://open.feishu.cn/document/sso/web-application-sso |
| WeChat | ✅ Planned | WeChat Open Platform |
| QQ | ✅ Planned | QQ Connect |

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

This is Phase 1 implementation. The following phases are planned:

- ✅ **Phase 1**: Plugin architecture and foundation
- ⏳ **Phase 2**: Database schema and models
- ⏳ **Phase 3**: Service layer and business logic
- ⏳ **Phase 4**: Controllers and API endpoints
- ⏳ **Phase 5**: Frontend implementation
- ⏳ **Phase 6**: Platform integrations
- ⏳ **Phase 7**: Security and testing

## Version

Current version: 1.0.0 (Phase 1 - Foundation)

## License

This plugin follows the same license as MineAdmin.