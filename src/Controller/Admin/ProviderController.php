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
use Hyperf\Swagger\Annotation\JsonContent;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\Put;
use Hyperf\Swagger\Annotation\RequestBody;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\PageResponse;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\MaimaiTech\OAuth2\Http\Request\CreateProviderRequest;
use Plugin\MaimaiTech\OAuth2\Http\Request\UpdateProviderRequest;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;
use Plugin\MaimaiTech\OAuth2\Service\ProviderService;

/**
 * OAuth提供者管理控制器.
 *
 * 提供OAuth第三方平台提供者的增删改查功能
 * 包括启用/禁用、测试连接等管理操作
 */
#[OA\HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
final class ProviderController extends AbstractController
{
    public function __construct(
        private readonly ProviderService $providerService
    ) {}

    /**
     * 获取所有OAuth提供者列表.
     */
    #[Get(
        path: '/admin/oauth/providers',
        operationId: 'listOAuthProviders',
        summary: '获取OAuth提供者列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:index')]
    #[PageResponse(
        instance: OAuthProvider::class,
        title: '提供者列表',
        description: 'OAuth提供者分页列表'
    )]
    public function index(): Result
    {
        $page = $this->getCurrentPage();
        $pageSize = $this->getPageSize();
        $data = $this->getRequestData();

        $result = $this->providerService->page($data, $page, $pageSize);

        return $this->success($result, '获取提供者列表成功');
    }

    /**
     * 创建新的OAuth提供者.
     */
    #[Post(
        path: '/admin/oauth/providers',
        operationId: 'createOAuthProvider',
        summary: '创建OAuth提供者',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:create')]
    #[ResultResponse(
        instance: new Result(data: OAuthProvider::class),
        title: '创建成功',
        description: '创建OAuth提供者成功返回对象'
    )]
    #[RequestBody(content: new JsonContent(
        ref: CreateProviderRequest::class,
        title: '创建提供者请求',
        required: ['name', 'display_name', 'client_id', 'client_secret', 'redirect_uri'],
        example: '{"name":"github","display_name":"GitHub","client_id":"your_client_id","client_secret":"your_client_secret","redirect_uri":"https://app.com/oauth/callback/github","scopes":["read:user","user:email"],"enabled":true}'
    ))]
    public function store(CreateProviderRequest $request): Result
    {
        $data = $request->validated();
        $provider = $this->providerService->create($data);

        return $this->success($provider, 'OAuth提供者创建成功');
    }

    /**
     * 获取单个OAuth提供者详情.
     */
    #[Get(
        path: '/admin/oauth/providers/{id}',
        operationId: 'getOAuthProvider',
        summary: '获取OAuth提供者详情',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:read')]
    #[ResultResponse(
        instance: new Result(data: OAuthProvider::class),
        title: '获取成功',
        description: 'OAuth提供者详情'
    )]
    #[OA\Parameter(
        name: 'id',
        description: '提供者ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    public function show(int $id): Result
    {
        $provider = $this->providerService->getById($id);

        return $this->success($provider, '获取提供者详情成功');
    }

    /**
     * 更新OAuth提供者信息.
     */
    #[Put(
        path: '/admin/oauth/providers/{id}',
        operationId: 'updateOAuthProvider',
        summary: '更新OAuth提供者',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:update')]
    #[OA\Parameter(
        name: 'id',
        description: '提供者ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[RequestBody(content: new JsonContent(
        ref: UpdateProviderRequest::class,
        title: '更新提供者请求',
        example: '{"display_name":"GitHub Updated","client_id":"new_client_id","scopes":["read:user","user:email","repo"],"enabled":false}'
    ))]
    public function update(int $id, UpdateProviderRequest $request): Result
    {
        $data = $request->validated();
        $provider = $this->providerService->update($id, $data);

        return $this->success($provider, 'OAuth提供者更新成功');
    }

    /**
     * 删除OAuth提供者.
     */
    #[Delete(
        path: '/admin/oauth/providers/{id}',
        operationId: 'deleteOAuthProvider',
        summary: '删除OAuth提供者',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:delete')]
    #[ResultResponse(
        instance: new Result(),
        title: '删除成功',
        example: '{"code":200,"message":"OAuth提供者删除成功","data":[]}'
    )]
    #[OA\Parameter(
        name: 'id',
        description: '提供者ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    public function destroy(int $id): Result
    {
        $this->providerService->delete($id);

        return $this->success([], 'OAuth提供者删除成功');
    }

    /**
     * 切换OAuth提供者启用状态
     */
    #[Post(
        path: '/admin/oauth/providers/{id}/toggle',
        operationId: 'toggleOAuthProvider',
        summary: '切换OAuth提供者启用状态',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:toggle')]
    #[ResultResponse(
        instance: new Result(data: OAuthProvider::class),
        title: '状态切换成功',
        description: '切换OAuth提供者状态成功返回对象'
    )]
    #[OA\Parameter(
        name: 'id',
        description: '提供者ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[RequestBody(content: new JsonContent(
        title: '切换状态请求',
        required: ['enabled'],
        properties: [
            new OA\Property(
                property: 'enabled',
                description: '是否启用',
                type: 'boolean',
                example: true
            ),
        ]
    ))]
    public function toggle(int $id): Result
    {
        $enabled = (bool) $this->getRequest()->input('enabled', true);
        $provider = $this->providerService->toggle($id, $enabled);

        return $this->success($provider, 'OAuth提供者状态切换成功');
    }

    /**
     * 测试OAuth提供者连接.
     */
    #[Post(
        path: '/admin/oauth/providers/{id}/test',
        operationId: 'testOAuthProvider',
        summary: '测试OAuth提供者连接',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2管理', 'OAuth提供者']
    )]
    #[Permission(code: 'oauth:provider:test')]
    #[ResultResponse(
        instance: new Result(),
        title: '测试完成',
        description: '测试OAuth提供者连接结果',
        example: '{"code":200,"message":"连接测试成功","data":{"status":"success","response_time":125,"auth_url":"https://github.com/login/oauth/authorize?client_id=xxx"}}'
    )]
    #[OA\Parameter(
        name: 'id',
        description: '提供者ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    public function test(int $id): Result
    {
        $result = $this->providerService->testConnection($id);

        return $this->success($result, '连接测试完成');
    }
}
