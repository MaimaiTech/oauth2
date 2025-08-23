<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace Plugin\MaimaiTech\OAuth2;

use App\Model\Permission\Menu;
use Hyperf\DbConnection\Db;

class InstallScript
{
    public function __invoke(): void
    {
        try {
            Db::beginTransaction();

            // 查找或创建系统管理菜单作为父级
            $systemMenu = Menu::updateOrCreate(
                ['name' => 'system', 'parent_id' => 0],
                [
                    'path' => '/system',
                    'component' => 'LAYOUT',
                    'redirect' => '',
                    'status' => 1,
                    'sort' => 0,
                    'meta' => [
                        'title' => '系统管理',
                        'icon' => 'solar:settings-bold-duotone',
                        'type' => 'M',
                        'hidden' => false,
                        'breadcrumbEnable' => true,
                        'copyright' => false,
                        'cache' => true,
                    ],
                ]
            );

            // 创建OAuth2管理父菜单
            $oauth2Menu = Menu::updateOrCreate(
                ['name' => 'system:oauth2'],
                [
                    'parent_id' => $systemMenu->id,
                    'path' => '/system/oauth2',
                    'component' => 'LAYOUT',
                    'redirect' => '/system/oauth2/providers',
                    'status' => 1,
                    'sort' => 80,
                    'meta' => [
                        'title' => 'OAuth2管理',
                        'icon' => 'solar:shield-keyhole-bold-duotone',
                        'type' => 'M',
                        'hidden' => false,
                        'breadcrumbEnable' => true,
                        'copyright' => false,
                        'cache' => true,
                    ],
                ]
            );

            // 创建OAuth2服务提供商配置菜单
            $providersMenu = Menu::updateOrCreate(
                ['name' => 'system:oauth2:providers'],
                [
                    'parent_id' => $oauth2Menu->id,
                    'path' => '/system/oauth2/providers',
                    'component' => 'maimaitech/oauth2/views/provider/index',
                    'redirect' => '',
                    'status' => 1,
                    'sort' => 10,
                    'meta' => [
                        'title' => 'OAuth2服务商配置',
                        'icon' => 'solar:settings-minimalistic-bold-duotone',
                        'type' => 'M',
                        'hidden' => false,
                        'breadcrumbEnable' => true,
                        'copyright' => false,
                        'cache' => true,
                        'componentPath' => 'plugins/',
                        'componentSuffix' => '.vue',
                    ],
                ]
            );

            // 创建用户绑定管理菜单
            $bindingsMenu = Menu::updateOrCreate(
                ['name' => 'system:oauth2:bindings'],
                [
                    'parent_id' => $oauth2Menu->id,
                    'path' => '/system/oauth2/bindings',
                    'component' => 'maimaitech/oauth2/views/bindings/index',
                    'redirect' => '',
                    'status' => 1,
                    'sort' => 20,
                    'meta' => [
                        'title' => '用户绑定管理',
                        'icon' => 'solar:users-group-two-rounded-bold-duotone',
                        'type' => 'M',
                        'hidden' => false,
                        'breadcrumbEnable' => true,
                        'copyright' => false,
                        'cache' => true,
                        'componentPath' => 'plugins/',
                        'componentSuffix' => '.vue',
                    ],
                ]
            );

            // 创建OAuth2统计菜单
            Menu::updateOrCreate(
                ['name' => 'system:oauth2:statistics'],
                [
                    'parent_id' => $oauth2Menu->id,
                    'path' => '/system/oauth2/statistics',
                    'component' => 'maimaitech/oauth2/views/statistics/index',
                    'redirect' => '',
                    'status' => 1,
                    'sort' => 30,
                    'meta' => [
                        'title' => 'OAuth2统计分析',
                        'icon' => 'solar:chart-bold-duotone',
                        'type' => 'M',
                        'hidden' => false,
                        'breadcrumbEnable' => true,
                        'copyright' => false,
                        'cache' => true,
                        'componentPath' => 'plugins/',
                        'componentSuffix' => '.vue',
                    ],
                ]
            );

            // 创建OAuth2回调处理页面（隐藏菜单，仅用于路由）
            Menu::updateOrCreate(
                ['name' => 'system:oauth2:callback'],
                [
                    'parent_id' => $oauth2Menu->id,
                    'path' => '/system/oauth2/callback/:provider',
                    'component' => 'maimaitech/oauth2/views/callback/index',
                    'redirect' => '',
                    'status' => 1,
                    'sort' => 40,
                    'meta' => [
                        'title' => 'OAuth2授权回调',
                        'icon' => 'solar:refresh-circle-bold-duotone',
                        'type' => 'M',
                        'hidden' => true, // 隐藏此菜单，仅作为路由使用
                        'breadcrumbEnable' => false,
                        'copyright' => false,
                        'cache' => false, // 回调页面不缓存
                        'componentPath' => 'plugins/',
                        'componentSuffix' => '.vue',
                    ],
                ]
            );

            // 创建OAuth2服务商配置的按钮权限
            $providerButtons = [
                [
                    'code' => 'system:oauth2:providers:list',
                    'title' => '查看服务商列表',
                    'i18n' => 'menu.oauth2.providers.list',
                ],
                [
                    'code' => 'system:oauth2:providers:create',
                    'title' => '创建服务商配置',
                    'i18n' => 'menu.oauth2.providers.create',
                ],
                [
                    'code' => 'system:oauth2:providers:update',
                    'title' => '更新服务商配置',
                    'i18n' => 'menu.oauth2.providers.update',
                ],
                [
                    'code' => 'system:oauth2:providers:delete',
                    'title' => '删除服务商配置',
                    'i18n' => 'menu.oauth2.providers.delete',
                ],
                [
                    'code' => 'system:oauth2:providers:toggle',
                    'title' => '启用/禁用服务商',
                    'i18n' => 'menu.oauth2.providers.toggle',
                ],
            ];

            foreach ($providerButtons as $button) {
                Menu::updateOrCreate(
                    ['name' => $button['code']],
                    [
                        'parent_id' => $providersMenu->id,
                        'path' => '',
                        'component' => '',
                        'redirect' => '',
                        'status' => 1,
                        'sort' => 0,
                        'meta' => [
                            'title' => $button['title'],
                            'i18n' => $button['i18n'],
                            'type' => 'B',
                        ],
                    ]
                );
            }

            // 创建用户绑定管理的按钮权限
            $bindingButtons = [
                [
                    'code' => 'system:oauth2:bindings:list',
                    'title' => '查看用户绑定',
                    'i18n' => 'menu.oauth2.bindings.list',
                ],
                [
                    'code' => 'system:oauth2:bindings:view',
                    'title' => '查看绑定详情',
                    'i18n' => 'menu.oauth2.bindings.view',
                ],
                [
                    'code' => 'system:oauth2:bindings:unbind',
                    'title' => '强制解绑账户',
                    'i18n' => 'menu.oauth2.bindings.unbind',
                ],
            ];

            foreach ($bindingButtons as $button) {
                Menu::updateOrCreate(
                    ['name' => $button['code']],
                    [
                        'parent_id' => $bindingsMenu->id,
                        'path' => '',
                        'component' => '',
                        'redirect' => '',
                        'status' => 1,
                        'sort' => 0,
                        'meta' => [
                            'title' => $button['title'],
                            'i18n' => $button['i18n'],
                            'type' => 'B',
                        ],
                    ]
                );
            }

            echo "OAuth2 Plugin installed successfully!\n";
            echo "✓ Created/Updated menu: OAuth2管理 (system:oauth2)\n";
            echo "✓ Created/Updated submenu: OAuth2服务商配置 (system:oauth2:providers)\n";
            echo "✓ Created/Updated submenu: 用户绑定管理 (system:oauth2:bindings)\n";
            echo "✓ Created/Updated submenu: OAuth2统计分析 (system:oauth2:statistics)\n";
            echo "✓ Created/Updated hidden route: OAuth2授权回调 (system:oauth2:callback)\n";
            echo "✓ Created/Updated permissions for providers management:\n";
            foreach ($providerButtons as $button) {
                echo "  - {$button['code']}: {$button['title']}\n";
            }
            echo "✓ Created/Updated permissions for bindings management:\n";
            foreach ($bindingButtons as $button) {
                echo "  - {$button['code']}: {$button['title']}\n";
            }

            Db::commit();

            echo "\nAPI Endpoints will be available:\n";
            echo "Admin Endpoints:\n";
            echo "- GET /admin/oauth2/providers - List OAuth2 providers\n";
            echo "- POST /admin/oauth2/providers - Create OAuth2 provider\n";
            echo "- PUT /admin/oauth2/providers/{id} - Update OAuth2 provider\n";
            echo "- DELETE /admin/oauth2/providers/{id} - Delete OAuth2 provider\n";
            echo "- POST /admin/oauth2/providers/{id}/toggle - Enable/disable provider\n";
            echo "- GET /admin/oauth2/bindings - List user OAuth2 bindings\n";
            echo "- DELETE /admin/oauth2/bindings/{id} - Force unbind user account\n";
            echo "\nUser Endpoints:\n";
            echo "- GET /oauth2/authorize/{provider} - Start OAuth2 flow\n";
            echo "- GET /oauth2/callback/{provider} - Handle OAuth2 callback\n";
            echo "- POST /oauth2/bind/{provider} - Bind OAuth2 account\n";
            echo "- DELETE /oauth2/unbind/{provider} - Unbind OAuth2 account\n";
            echo "- GET /oauth2/bindings - Get current user's bindings\n";
        } catch (\Exception $e) {
            Db::rollBack();
            throw new \RuntimeException('Failed to install OAuth2 Plugin: ' . $e->getMessage());
        }
    }
}
