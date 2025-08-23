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

namespace Plugin\MaimaiTech\OAuth2\Controller\User;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\JsonContent;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\RequestBody;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\MaimaiTech\OAuth2\Http\Request\AuthorizeRequest;
use Plugin\MaimaiTech\OAuth2\Http\Request\BindAccountRequest;
use Plugin\MaimaiTech\OAuth2\Http\Request\CallbackRequest;
use Plugin\MaimaiTech\OAuth2\Http\Request\UnbindAccountRequest;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;
use Plugin\MaimaiTech\OAuth2\Model\UserOAuthAccount;
use Plugin\MaimaiTech\OAuth2\Service\OAuthService;

/**
 * 用户OAuth控制器.
 *
 * 处理用户端OAuth认证流程，包括授权跳转、回调处理、账号绑定解绑等
 * 支持多种第三方平台的OAuth 2.0认证流程
 */
#[OA\HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
final class OAuthController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly CurrentUser $currentUser
    ) {}

    /**
     * 发起OAuth授权请求
     *
     * 生成OAuth授权链接，用户将被重定向到第三方平台进行授权
     */
    #[Get(
        path: '/oauth/authorize/{provider}',
        operationId: 'oauthAuthorize',
        description: '发起OAuth授权请求，生成第三方平台授权链接并重定向用户',
        summary: 'OAuth授权跳转',
        tags: ['OAuth2认证', '用户认证']
    )]
    #[ResultResponse(
        instance: new Result(),
        title: '授权跳转',
        description: 'OAuth授权跳转结果，通常为重定向响应',
        example: '{"code":200,"message":"正在跳转到授权页面","data":{"auth_url":"https://github.com/login/oauth/authorize?client_id=xxx&redirect_uri=xxx&state=xxx","state":"random_state_string"}}'
    )]
    #[OA\Parameter(
        name: 'provider',
        description: 'OAuth提供者名称',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            enum: ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq'],
            example: 'github'
        )
    )]
    #[OA\Parameter(
        name: 'redirect_uri',
        description: '授权成功后的回调地址（可选，使用配置的默认值）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'https://app.com/profile/oauth')
    )]
    #[OA\Parameter(
        name: 'bind_user',
        description: '是否绑定到当前用户（需要登录）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean', example: true)
    )]
    public function authorize(string $provider, AuthorizeRequest $request, ResponseInterface $response): mixed
    {
        $data = $request->validated();
        $redirectUri = $data['redirect_uri'] ?? null;
        $userId = $this->currentUser->id();

        $authResult = $this->oauthService->getAuthorizationUrl($provider, $userId, $redirectUri, $request->getClientIp(), $request->getHeaderLine('User-Agent'));

        // 如果客户端期望JSON响应（API调用）
        if ($request->hasHeader('Accept') && str_contains($request->header('Accept'), 'application/json')) {
            return $this->success($authResult, '授权链接生成成功');
        }

        // 否则直接重定向到授权页面（浏览器访问）
        return $response->redirect($authResult);
    }

    /**
     * 处理OAuth授权回调.
     *
     * 处理第三方平台的OAuth授权回调，完成用户信息获取和账号绑定
     */
    #[Get(
        path: '/oauth/callback/{provider}',
        operationId: 'oauthCallback',
        description: '处理第三方平台OAuth授权回调，获取用户信息并处理账号绑定或登录',
        summary: 'OAuth授权回调',
        tags: ['OAuth2认证', '用户认证']
    )]
    #[ResultResponse(
        instance: new Result(),
        title: '授权回调处理',
        description: 'OAuth授权回调处理结果',
        example: '{"code":200,"message":"授权成功","data":{"action":"login","user":{"id":1,"username":"john","nickname":"John Doe"},"oauth_account":{"provider":"github","provider_username":"johndoe","provider_email":"john@example.com"}}}'
    )]
    #[OA\Parameter(
        name: 'provider',
        description: 'OAuth提供者名称',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            enum: ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq'],
            example: 'github'
        )
    )]
    #[OA\Parameter(
        name: 'code',
        description: '授权码',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'authorization_code_from_provider')
    )]
    #[OA\Parameter(
        name: 'state',
        description: '状态参数（CSRF保护）',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'random_state_string')
    )]
    #[OA\Parameter(
        name: 'error',
        description: '错误码（授权失败时）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'access_denied')
    )]
    #[OA\Parameter(
        name: 'error_description',
        description: '错误描述（授权失败时）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'The user denied the request')
    )]
    public function callback(string $provider, CallbackRequest $request, ResponseInterface $response): mixed
    {
        $data = $request->validated();

        // 检查是否有错误参数
        if (isset($data['error'])) {
            $errorMessage = $data['error_description'] ?? $data['error'];
            return $this->error("OAuth授权失败: {$errorMessage}");
        }

        $result = $this->oauthService->handleCallback($provider, $data['code'], $data['state'], $request->getClientIp());

        // 根据结果类型决定响应方式
        if ($result['action'] === 'redirect') {
            // 重定向到指定页面
            return $response->redirect($result['redirect_url']);
        }

        return $this->success($result, 'OAuth授权处理成功');
    }

    /**
     * 绑定OAuth账号到当前用户.
     *
     * 需要用户已登录，将指定的OAuth提供者账号绑定到当前用户
     */
    #[Post(
        path: '/oauth/bind/{provider}',
        operationId: 'bindOAuthAccount',
        description: '将指定OAuth提供者账号绑定到当前登录用户',
        summary: '绑定OAuth账号',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2认证', '账号管理']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(
        instance: new Result(data: UserOAuthAccount::class),
        title: '绑定成功',
        description: 'OAuth账号绑定成功返回对象'
    )]
    #[OA\Parameter(
        name: 'provider',
        description: 'OAuth提供者名称',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            enum: ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq'],
            example: 'github'
        )
    )]
    #[RequestBody(content: new JsonContent(
        ref: BindAccountRequest::class,
        title: '绑定账号请求',
        required: ['access_token'],
        example: '{"access_token":"oauth_access_token_from_callback","provider_data":{"id":"12345","login":"johndoe","email":"john@example.com","name":"John Doe","avatar_url":"https://github.com/johndoe.png"}}'
    ))]
    public function bind(string $provider, BindAccountRequest $request): Result
    {
        $data = $request->validated();
        $userId = $this->currentUser->id();

        $binding = $this->oauthService->bindAccount($userId, $provider, $data, [], $request->getClientIp());

        return $this->success($binding, 'OAuth账号绑定成功');
    }

    /**
     * 解绑OAuth账号.
     *
     * 解绑当前用户的指定OAuth提供者账号
     */
    #[Delete(
        path: '/oauth/unbind/{provider}',
        operationId: 'unbindOAuthAccount',
        description: '解绑当前用户的指定OAuth提供者账号',
        summary: '解绑OAuth账号',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2认证', '账号管理']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(
        instance: new Result(),
        title: '解绑成功',
        example: '{"code":200,"message":"OAuth账号解绑成功","data":{"user_id":1,"provider":"github","unbound_at":"2025-08-22 12:00:00"}}'
    )]
    #[OA\Parameter(
        name: 'provider',
        description: 'OAuth提供者名称',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            enum: ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq'],
            example: 'github'
        )
    )]
    #[RequestBody(content: new JsonContent(
        ref: UnbindAccountRequest::class,
        title: '解绑账号请求',
        example: '{"confirm":true,"password":"user_current_password"}'
    ))]
    public function unbind(string $provider, UnbindAccountRequest $request): Result
    {
        $data = $request->validated();
        $userId = $this->currentUser->id();

        $result = $this->oauthService->unbindAccount($userId, $provider, $data);

        return $this->success($result, 'OAuth账号解绑成功');
    }

    /**
     * 获取当前用户的OAuth绑定列表.
     *
     * 返回当前用户所有已绑定的OAuth账号信息
     */
    #[Get(
        path: '/oauth/bindings',
        operationId: 'getUserOAuthBindings',
        description: '获取当前用户所有已绑定的OAuth第三方账号列表',
        summary: '获取我的OAuth绑定',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2认证', '账号管理']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(
        instance: new Result(data: [UserOAuthAccount::class]),
        title: '绑定列表',
        description: '用户OAuth绑定列表'
    )]
    public function bindings(): Result
    {
        $userId = $this->currentUser->id();
        $bindings = $this->oauthService->getUserBindings($userId);

        return $this->success($bindings, '获取OAuth绑定列表成功');
    }

    /**
     * 获取可用的OAuth提供者列表.
     *
     * 返回系统配置的所有可用OAuth提供者信息
     */
    #[Get(
        path: '/oauth/providers',
        operationId: 'getAvailableOAuthProviders',
        description: '获取系统配置的所有可用OAuth提供者列表，用于前端显示登录/绑定按钮',
        summary: '获取可用OAuth提供者',
        tags: ['OAuth2认证', '系统配置']
    )]
    #[ResultResponse(
        instance: new Result(data: [OAuthProvider::class]),
        title: '提供者列表',
        description: '可用OAuth提供者列表'
    )]
    public function providers(): Result
    {
        $userId = $this->currentUser->id();
        $providers = $this->oauthService->getAvailableProvidersForUser($userId);

        return $this->success($providers, '获取可用提供者列表成功');
    }

    /**
     * 刷新OAuth访问令牌.
     *
     * 使用refresh_token刷新指定提供者的访问令牌
     */
    #[Post(
        path: '/oauth/refresh/{provider}',
        operationId: 'refreshOAuthToken',
        summary: '刷新OAuth令牌',
        description: '刷新指定OAuth提供者的访问令牌',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['OAuth2认证', '令牌管理']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(
        instance: new Result(),
        title: '令牌刷新',
        description: '令牌刷新结果',
        example: '{"code":200,"message":"令牌刷新成功","data":{"access_token":"new_access_token","refresh_token":"new_refresh_token","expires_at":"2025-08-23 12:00:00"}}'
    )]
    #[OA\Parameter(
        name: 'provider',
        description: 'OAuth提供者名称',
        in: 'path',
        required: true,
        schema: new OA\Schema(
            type: 'string',
            enum: ['dingtalk', 'github', 'gitee', 'feishu', 'wechat', 'qq'],
            example: 'github'
        )
    )]
    public function refreshToken(string $provider): Result
    {
        $userId = $this->currentUser->id();
        $result = $this->oauthService->refreshTokens($userId, $provider);

        return $this->success($result, 'OAuth令牌刷新成功');
    }
}
