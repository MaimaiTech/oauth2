/**
 * MineAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using MineAdmin.
 *
 * @Author MaimaiTech
 * @Link   https://github.com/mineadmin
 */
import type { App } from 'vue'
import type { Plugin } from '#/global'

// Export API functions with namespace to avoid conflicts
export * as OAuthAdminAPI from './api/oauthApi'

// Export types and interfaces
export * from './api/types'
export * as OAuthUserAPI from './api/userOAuthApi'

// Export component modules
export * from './components/oauth'
export * from './components/user'

const pluginConfig: Plugin.PluginConfig = {
  install(_app: App) {
    // Vue plugin installation hook
    console.log('OAuth2 Plugin installed successfully')
    console.log('Plugin components and APIs are now available for import')

    const urlObj = new URL(window.location.href)
    const code = urlObj.searchParams.get('code') || urlObj.searchParams.get('authCode')
    const state = urlObj.searchParams.get('state')
    const hashPath = urlObj.hash.split('?')[0] // 原始 Hash 路径

    if (code && state) {
      const fixedUrl = `${urlObj.origin}${hashPath}?code=${code}&state=${state}`
      window.location.href = fixedUrl
    }
  },
  config: {
    enable: true,
    info: {
      name: 'maimaitech/oauth2',
      version: '1.0.0',
      author: 'MaimaiTech',
      description: 'OAuth2 第三方登录管理插件，支持钉钉、GitHub、码云等多种平台',
    },
  },
  hooks:{
    setup(){
      const settingStore = useSettingStore()
      const settings = settingStore.getSettings('app')
      settings.whiteRoute.push('OAuthLoginCallback')
      settingStore.setSettings(settings,'app')
    }
  },
  // Views/Routes are registered dynamically by InstallScript.php
  // This ensures proper menu permissions and supports dynamic plugin installation
  views: [
  ],
}

export default pluginConfig
