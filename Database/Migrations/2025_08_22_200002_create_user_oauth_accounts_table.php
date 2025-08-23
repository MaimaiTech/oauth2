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
        Schema::create('user_oauth_accounts', static function (Blueprint $table) {
            $table->comment('用户OAuth2账户绑定表');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->unsigned()->comment('用户ID');
            $table->string('provider', 50)->comment('OAuth服务商(dingtalk,github,gitee,feishu,wechat,qq)');
            $table->string('provider_user_id', 100)->comment('第三方平台用户ID');
            $table->string('provider_username', 100)->nullable()->comment('第三方平台用户名');
            $table->string('provider_email', 100)->nullable()->comment('第三方平台邮箱');
            $table->string('provider_avatar', 500)->nullable()->comment('第三方平台头像URL');
            $table->json('provider_data')->nullable()->comment('第三方平台原始用户数据');
            $table->text('access_token')->nullable()->comment('访问令牌(加密存储)');
            $table->text('refresh_token')->nullable()->comment('刷新令牌(加密存储)');
            $table->timestamp('token_expires_at')->nullable()->comment('令牌过期时间');
            $table->tinyInteger('status')->default(1)->comment('状态:1=正常,2=停用');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->ipAddress('last_login_ip')->nullable()->comment('最后登录IP');
            $table->datetimes();
            $table->string('remark', 255)->default('')->comment('备注');

            // 外键约束
            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');

            // 唯一约束：一个用户在同一平台只能绑定一个账号
            $table->unique(['user_id', 'provider']);

            // 唯一约束：一个第三方账号只能绑定一个用户
            $table->unique(['provider', 'provider_user_id']);

            // 添加索引
            $table->index(['provider', 'provider_user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_oauth_accounts');
    }
};
