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

use App\Repository\Permission\MenuRepository;
use Hyperf\DbConnection\Db;

class UninstallScript
{
    public function __invoke(): void
    {
        try {
            Db::beginTransaction();

            $menuRepository = \Hyperf\Support\make(MenuRepository::class);

            // 查找OAuth2管理菜单
            $oauth2Menu = $menuRepository->getQuery()
                ->where('name', 'system:oauth2')
                ->first();

            $deletedMenus = [];
            $deletedButtons = [];

            if ($oauth2Menu) {
                // 查找并删除所有子菜单及其按钮权限
                $subMenus = $menuRepository->getQuery()
                    ->where('parent_id', $oauth2Menu->id)
                    ->get();

                foreach ($subMenus as $subMenu) {
                    $deletedMenus[] = $subMenu->name;

                    // 删除子菜单的按钮权限
                    $buttonPermissions = $menuRepository->getQuery()
                        ->where('parent_id', $subMenu->id)
                        ->get();

                    foreach ($buttonPermissions as $permission) {
                        $deletedButtons[] = $permission->name;
                        $permission->delete();
                    }

                    // 删除子菜单
                    $subMenu->delete();
                }

                // 删除主菜单
                $oauth2Menu->delete();
                $deletedMenus[] = $oauth2Menu->name;

                echo "OAuth2 Plugin uninstalled successfully!\n";
                echo "✓ Removed main menu: OAuth2管理 (system:oauth2)\n";
                if (! empty($deletedMenus)) {
                    echo "✓ Removed submenus:\n";
                    foreach ($deletedMenus as $menu) {
                        if ($menu !== 'system:oauth2') {
                            echo "  - {$menu}\n";
                        }
                    }
                }
                if (! empty($deletedButtons)) {
                    echo "✓ Removed permissions:\n";
                    foreach ($deletedButtons as $button) {
                        echo "  - {$button}\n";
                    }
                }
            } else {
                echo "OAuth2 Plugin menu not found, skipping menu cleanup.\n";
            }

            Db::commit();

            echo "\nNote: Plugin data (oauth_providers, user_oauth_accounts, oauth_states) tables are preserved.\n";
            echo "To completely remove all data, you may manually drop these tables:\n";
            echo "- DROP TABLE IF EXISTS oauth_providers;\n";
            echo "- DROP TABLE IF EXISTS user_oauth_accounts;\n";
            echo "- DROP TABLE IF EXISTS oauth_states;\n";
            echo "\nAll plugin routes and services have been removed.\n";
        } catch (\Exception $e) {
            Db::rollBack();
            throw new \RuntimeException('Failed to uninstall OAuth2 Plugin: ' . $e->getMessage());
        }
    }
}
