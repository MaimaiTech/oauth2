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
        Schema::create('oauth_states', static function (Blueprint $table) {
            $table->comment('OAuth2状态参数表(CSRF防护)');
            $table->bigIncrements('id')->comment('主键');
            $table->string('state', 64)->unique()->comment('状态参数(随机字符串)');
            $table->string('provider', 50)->comment('OAuth服务商');
            $table->bigInteger('user_id')->unsigned()->nullable()->comment('用户ID(可为空,支持匿名OAuth流程)');
            $table->json('payload')->nullable()->comment('附加数据(回调时需要的额外信息)');
            $table->ipAddress('client_ip')->nullable()->comment('客户端IP');
            $table->string('user_agent', 500)->nullable()->comment('用户代理');
            $table->timestamp('expires_at')->comment('过期时间(默认15分钟)');
            $table->tinyInteger('status')->default(1)->comment('状态:1=有效,2=已使用,3=已过期');
            $table->timestamp('used_at')->nullable()->comment('使用时间');
            $table->datetimes();

            // 添加索引
            $table->index(['state', 'provider']);
            $table->index(['provider', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('client_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_states');
    }
};
