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

namespace Plugin\MaimaiTech\OAuth2\Controller;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Result;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Swagger\Annotation\Get;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\MaimaiTech\OAuth2\Exception\OAuthException;
use Plugin\MaimaiTech\OAuth2\Http\Request\AuthorizeRequest;
use Plugin\MaimaiTech\OAuth2\Http\Request\CallbackRequest;
use Plugin\MaimaiTech\OAuth2\Model\OAuthProvider;
use Plugin\MaimaiTech\OAuth2\Service\OAuthService;

/**
 * OAuth2 登录控制器.
 *
 * 提供OAuth2第三方平台登录功能，支持多种OAuth提供者
 * 不需要用户预先登录，可直接通过第三方平台进行身份验证和登录
 */
#[OA\HyperfServer(name: 'http')]
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService
    ) {}

    /**
     * 发起OAuth登录请求
     *
     * 生成OAuth授权链接，用户将被重定向到第三方平台进行登录认证
     */
    #[OA\Post(
        path: '/passport/oauth/{provider}',
        operationId: 'oauthLogin',
        description: '发起OAuth登录请求，生成第三方平台登录授权链接并重定向用户',
        summary: 'OAuth登录跳转',
        tags: ['OAuth2登录', '用户认证']
    )]
    #[ResultResponse(
        instance: new Result(),
        title: '登录跳转',
        description: 'OAuth登录跳转结果，通常为重定向响应或授权链接',
        example: '{"code":200,"message":"正在跳转到登录页面","data":{"auth_url":"https://github.com/login/oauth/authorize?client_id=xxx&redirect_uri=xxx&state=xxx","state":"random_state_string"}}'
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
        description: '登录成功后的回调地址（可选）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'https://app.com/dashboard')
    )]
    public function login(string $provider, AuthorizeRequest $request, ResponseInterface $response): mixed
    {
        $data = $request->validated();
        $redirectUri = $data['redirect_uri'] ?? null;

        $authUrl = $this->oauthService->getOAuthLoginUrl(
            $provider,
            $redirectUri,
            $request->getClientIp(),
            $request->getHeaderLine('User-Agent')
        );

        // 如果客户端期望JSON响应（API调用）
        if ($request->hasHeader('Accept') && str_contains($request->header('Accept'), 'application/json')) {
            return $this->success(['auth_url' => $authUrl, 'provider' => $provider], '登录授权链接生成成功');
        }

        // 否则直接重定向到授权页面（浏览器访问）
        return $response->redirect($authUrl);
    }

    /**
     * 处理OAuth登录回调.
     *
     * 处理第三方平台的OAuth登录回调，完成用户身份验证并返回JWT令牌
     * @throws OAuthException
     */
    #[Get(
        path: '/passport/oauth/login/callback/{provider}',
        operationId: 'oauthLoginCallback',
        description: '处理第三方平台OAuth登录回调，获取用户信息并返回JWT令牌完成登录',
        summary: 'OAuth登录回调',
        tags: ['OAuth2登录', '用户认证']
    )]
    #[ResultResponse(
        instance: new Result(),
        title: '登录回调处理',
        description: 'OAuth登录回调处理结果，包含用户信息和JWT令牌',
        example: '{"code":200,"message":"登录成功","data":{"action":"login","user":{"id":1,"username":"john","nickname":"John Doe","email":"john@example.com","avatar":"https://avatar.url"},"oauth_account":{"provider":"github","provider_username":"johndoe","provider_email":"john@example.com"},"tokens":{"access_token":"jwt_access_token","refresh_token":"jwt_refresh_token","expire_at":3600}}}'
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
        description: '错误码（登录失败时）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'access_denied')
    )]
    #[OA\Parameter(
        name: 'error_description',
        description: '错误描述（登录失败时）',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'The user denied the request')
    )]
    public function callback(string $provider, CallbackRequest $request): Result
    {
        $data = $request->validated();

        // 检查是否有错误参数
        if (isset($data['error'])) {
            $errorMessage = $data['error_description'] ?? $data['error'];
            return $this->error("OAuth登录失败: {$errorMessage}");
        }

        $result = $this->oauthService->handleOAuthLogin(
            $provider,
            $data['code'],
            $data['state'],
            $request->getClientIp(),
            $request->getHeaderLine('User-Agent')
        );

        return $this->success($result, 'OAuth登录成功');
    }

    /**
     * 获取可用的OAuth登录提供者列表.
     *
     * 返回系统配置的所有可用OAuth提供者信息，用于前端显示登录按钮
     */
    #[Get(
        path: '/passport/oauth/login/providers',
        operationId: 'getOAuthLoginProviders',
        description: '获取系统配置的所有可用OAuth登录提供者列表，用于前端显示登录按钮',
        summary: '获取OAuth登录提供者',
        tags: ['OAuth2登录', '系统配置']
    )]
    #[ResultResponse(
        instance: new Result(data: [OAuthProvider::class]),
        title: '登录提供者列表',
        description: '可用OAuth登录提供者列表'
    )]
    public function providers(): Result
    {
        $providers = $this->oauthService->getAvailableProviders();
        return $this->success($providers, '获取可用登录提供者列表成功');
    }
}
