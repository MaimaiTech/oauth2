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
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oauth_providers', static function (Blueprint $table) {
            $table->comment('OAuth2服务提供商配置表');
            $table->bigIncrements('id')->comment('主键');
            $table->string('name', 50)->unique()->comment('服务商名称(dingtalk,github,gitee,feishu,wechat,qq)');
            $table->string('display_name', 100)->comment('显示名称');
            $table->string('client_id', 255)->comment('应用ID/客户端ID');
            $table->string('client_secret', 500)->comment('应用密钥/客户端密钥(加密存储)');
            $table->text('redirect_uri')->comment('回调地址');
            $table->json('scopes')->nullable()->comment('OAuth授权范围');
            $table->json('extra_config')->nullable()->comment('平台特定配置参数');
            $table->tinyInteger('enabled')->default(1)->comment('启用状态:1=启用,2=禁用');
            $table->tinyInteger('status')->default(1)->comment('状态:1=正常,2=停用');
            $table->integer('sort')->default(0)->comment('排序');
            $table->authorBy();
            $table->datetimes();
            $table->softDeletes();
            $table->string('remark', 255)->default('')->comment('备注');

            // 添加索引
            $table->index(['name', 'enabled']);
            $table->index(['enabled', 'status']);
            $table->index('sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_providers');
    }
};
