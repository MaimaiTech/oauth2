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

namespace Plugin\MaimaiTech\OAuth2\Controller\Admin;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\PageResponse;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\MaimaiTech\OAuth2\Model\UserOAuthAccount;
use Plugin\MaimaiTech\OAuth2\Service\OAuthService;

/**
 * 用户OAuth绑定管理控制器.
 *
 * 为管理员提供用户OAuth账号绑定的管理功能
 * 包括查看所有用户绑定、强制解绑等操作
 */
#[OA\HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
final class UserBindingController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService
    ) {}

    /**
     * 获取所有用户OAuth绑定列表.
     */
    #[Get(
        path: '/admin/oauth/bindings',
        operationId: 'listUserOAuthBindings',
        description: '管理员查看所有用户的OAuth第三方账号绑定情况，支持分页和筛选',
        summary: '获取用户OAuth绑定列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', '用户绑定']
    )]
    #[Permission(code: 'oauth:binding:index')]
    #[PageResponse(
        instance: UserOAuthAccount::class,
        title: '绑定列表',
        description: '用户OAuth绑定分页列表'
    )]
    #[OA\Parameter(
        name: 'page',
        description: '页码',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Parameter(
        name: 'page_size',
        description: '每页数量',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 10, maximum: 100)
    )]
    #[OA\Parameter(
        name: 'provider',
        description: '过滤指定提供者',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'github')
    )]
    #[OA\Parameter(
        name: 'user_id',
        description: '过滤指定用户',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Parameter(
        name: 'username',
        description: '模糊搜索用户名',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'john')
    )]
    #[OA\Parameter(
        name: 'provider_username',
        description: '模糊搜索第三方用户名',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'johndoe')
    )]
    public function index(): Result
    {
        $page = $this->getCurrentPage();
        $pageSize = $this->getPageSize();
        $filters = $this->getRequestData();

        $result = $this->oauthService->listAllUserBindings($filters, $page, $pageSize);

        return $this->success($result, '获取用户绑定列表成功');
    }

    /**
     * 获取指定用户的OAuth绑定.
     */
    #[Get(
        path: '/admin/oauth/bindings/user/{userId}',
        operationId: 'getUserOAuthBindings',
        description: '管理员查看指定用户的所有OAuth第三方账号绑定',
        summary: '获取用户OAuth绑定',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', '用户绑定']
    )]
    #[Permission(code: 'oauth:binding:read')]
    #[ResultResponse(
        instance: new Result(data: [UserOAuthAccount::class]),
        title: '绑定详情',
        description: '用户OAuth绑定详情列表'
    )]
    #[OA\Parameter(
        name: 'userId',
        description: '用户ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    public function getUserBindings(int $userId): Result
    {
        $bindings = $this->oauthService->getUserBindings($userId);

        return $this->success($bindings, '获取用户绑定详情成功');
    }

    /**
     * 管理员强制解绑用户OAuth账号.
     */
    #[Delete(
        path: '/admin/oauth/bindings/{id}',
        operationId: 'forceUnbindUserOAuth',
        description: '管理员强制解绑指定的用户OAuth第三方账号绑定关系',
        summary: '强制解绑用户OAuth账号',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', '用户绑定']
    )]
    #[Permission(code: 'oauth:binding:delete')]
    #[ResultResponse(
        instance: new Result(),
        title: '解绑成功',
        example: '{"code":200,"message":"强制解绑成功","data":{"user_id":1,"provider":"github","unbound_at":"2025-08-22 12:00:00"}}'
    )]
    #[OA\Parameter(
        name: 'id',
        description: '绑定记录ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    public function forceUnbind(int $id): Result
    {
        $result = $this->oauthService->forceUnbindAccount($id);

        return $this->success($result, '强制解绑成功');
    }

    /**
     * 获取OAuth绑定统计数据.
     */
    #[Get(
        path: '/admin/oauth/bindings/statistics',
        operationId: 'getOAuthBindingStatistics',
        description: '管理员查看OAuth绑定的统计数据，包括各提供者绑定数量、活跃度等',
        summary: '获取OAuth绑定统计',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', '数据统计']
    )]
    #[Permission(code: 'oauth:binding:statistics')]
    #[ResultResponse(
        instance: new Result(),
        title: '统计数据',
        description: 'OAuth绑定统计信息',
        example: '{"code":200,"message":"获取统计数据成功","data":{"total_bindings":156,"active_providers":{"github":89,"gitee":45,"dingtalk":22},"recent_bindings":12,"monthly_growth":8.5}}'
    )]
    #[OA\Parameter(
        name: 'period',
        description: '统计周期',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['day', 'week', 'month', 'year'], example: 'month')
    )]
    public function statistics(): Result
    {
        $period = $this->getRequest()->input('period', 'month');
        $stats = $this->oauthService->getBindingStatistics($period);

        return $this->success($stats, '获取统计数据成功');
    }

    /**
     * 批量操作用户OAuth绑定.
     */
    #[OA\Post(
        path: '/admin/oauth/bindings/batch',
        operationId: 'batchOperateBindings',
        summary: '批量操作OAuth绑定',
        description: '管理员对多个OAuth绑定进行批量操作，如批量解绑、批量启用等',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', '批量操作']
    )]
    #[Permission(code: 'oauth:binding:batch')]
    #[ResultResponse(
        instance: new Result(),
        title: '批量操作完成',
        description: '批量操作结果',
        example: '{"code":200,"message":"批量操作完成","data":{"success":8,"failed":2,"details":[{"id":1,"status":"success"},{"id":2,"status":"failed","reason":"账号不存在"}]}}'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(
        title: '批量操作请求',
        required: ['action', 'binding_ids'],
        properties: [
            new OA\Property(
                property: 'action',
                description: '操作类型',
                type: 'string',
                enum: ['unbind', 'disable', 'enable'],
                example: 'unbind'
            ),
            new OA\Property(
                property: 'binding_ids',
                description: '绑定记录ID列表',
                type: 'array',
                items: new OA\Items(type: 'integer'),
                example: [1, 2, 3, 4, 5]
            ),
            new OA\Property(
                property: 'reason',
                description: '操作原因（可选）',
                type: 'string',
                example: '违规账号清理'
            ),
        ]
    ))]
    public function batchOperate(): Result
    {
        $data = $this->getRequestData();
        $action = $data['action'] ?? '';
        $bindingIds = $data['binding_ids'] ?? [];
        $reason = $data['reason'] ?? null;

        $result = $this->oauthService->batchOperateBindings($action, $bindingIds, $reason);

        return $this->success($result, '批量操作完成');
    }

    /**
     * 导出OAuth绑定数据.
     */
    #[Get(
        path: '/admin/oauth/bindings/export',
        operationId: 'exportOAuthBindings',
        summary: '导出OAuth绑定数据',
        description: '管理员导出OAuth绑定数据到CSV或Excel格式',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', '数据导出']
    )]
    #[Permission(code: 'oauth:binding:export')]
    #[ResultResponse(
        instance: new Result(),
        title: '导出任务创建',
        description: '导出任务创建结果',
        example: '{"code":200,"message":"导出任务已创建","data":{"task_id":"export_oauth_20250822_120000","download_url":"/download/oauth-bindings-20250822.csv","estimated_time":30}}'
    )]
    #[OA\Parameter(
        name: 'format',
        description: '导出格式',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'], example: 'csv')
    )]
    #[OA\Parameter(
        name: 'provider',
        description: '过滤提供者',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'github')
    )]
    #[OA\Parameter(
        name: 'date_from',
        description: '开始日期',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date', example: '2025-01-01')
    )]
    #[OA\Parameter(
        name: 'date_to',
        description: '结束日期',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'date', example: '2025-08-22')
    )]
    public function export(): Result
    {
        $filters = $this->getRequestData();
        $format = $filters['format'] ?? 'csv';

        $result = $this->oauthService->exportBindings($filters, $format);

        return $this->success($result, '导出任务已创建');
    }
}
