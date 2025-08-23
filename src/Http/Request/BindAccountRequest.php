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

namespace Plugin\MaimaiTech\OAuth2\Http\Request;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Swagger\Annotation\Property;
use Hyperf\Swagger\Annotation\Schema;
use Hyperf\Validation\Request\FormRequest;
use Mine\Support\Request\ClientIpRequestTrait;
use Plugin\MaimaiTech\OAuth2\Service\OAuthClientFactory;

#[Schema(
    title: '绑定OAuth账户',
    description: '手动绑定OAuth账户到用户的请求参数',
    properties: [
        new Property('provider', description: 'OAuth提供者名称 (dingtalk, github, gitee, feishu, wechat, qq)', type: 'string'),
        new Property('user_id', description: '用户ID', type: 'integer'),
        new Property('provider_user_id', description: '第三方平台用户ID', type: 'string'),
        new Property('provider_username', description: '第三方平台用户名', type: 'string'),
        new Property('provider_email', description: '第三方平台邮箱', type: 'string'),
        new Property('provider_avatar', description: '第三方平台头像URL', type: 'string'),
        new Property('access_token', description: '访问令牌', type: 'string'),
        new Property('refresh_token', description: '刷新令牌', type: 'string'),
        new Property('expires_in', description: '令牌有效期(秒)', type: 'integer'),
        new Property('provider_data', description: '第三方平台完整用户数据', type: 'object'),
    ]
)]
class BindAccountRequest extends FormRequest
{
    use ClientIpRequestTrait;
    use NoAuthorizeTrait;

    public function rules(): array
    {
        $supportedProviders = implode(',', OAuthClientFactory::getAvailableProviders());

        return [
            'provider' => [
                'required',
                'string',
                "in:{$supportedProviders}",
            ],
            'user_id' => 'required|integer|exists:user,id',
            'provider_user_id' => 'required|string|max:255',
            'provider_username' => 'nullable|string|max:100',
            'provider_email' => 'nullable|email|max:100',
            'provider_avatar' => 'nullable|url|max:500',
            'access_token' => 'required|string',
            'refresh_token' => 'nullable|string',
            'expires_in' => 'nullable|integer|min:0',
            'provider_data' => 'nullable|array',
        ];
    }

    public function attributes(): array
    {
        return [
            'provider' => 'OAuth提供者',
            'user_id' => '用户ID',
            'provider_user_id' => '第三方用户ID',
            'provider_username' => '第三方用户名',
            'provider_email' => '第三方邮箱',
            'provider_avatar' => '第三方头像',
            'access_token' => '访问令牌',
            'refresh_token' => '刷新令牌',
            'expires_in' => '令牌有效期',
            'provider_data' => '第三方用户数据',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => '请选择OAuth提供者',
            'provider.in' => '不支持的OAuth提供者',
            'user_id.required' => '用户ID不能为空',
            'user_id.exists' => '用户不存在',
            'provider_user_id.required' => '第三方用户ID不能为空',
            'access_token.required' => '访问令牌不能为空',
            'provider_email.email' => '邮箱格式不正确',
            'provider_avatar.url' => '头像地址格式不正确',
        ];
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): string
    {
        return $this->input('provider');
    }

    /**
     * Get the user ID.
     */
    public function getUserId(): int
    {
        return (int) $this->input('user_id');
    }

    /**
     * Get user data for binding.
     */
    public function getUserData(): array
    {
        return [
            'id' => $this->input('provider_user_id'),
            'username' => $this->input('provider_username'),
            'name' => $this->input('provider_username'),
            'email' => $this->input('provider_email'),
            'avatar' => $this->input('provider_avatar'),
            'raw' => $this->input('provider_data', []),
        ];
    }

    /**
     * Get token data for binding.
     */
    public function getTokenData(): array
    {
        $tokenData = [
            'access_token' => $this->input('access_token'),
        ];

        if ($this->has('refresh_token')) {
            $tokenData['refresh_token'] = $this->input('refresh_token');
        }

        if ($this->has('expires_in')) {
            $tokenData['expires_in'] = $this->input('expires_in');
        }

        return $tokenData;
    }

    /**
     * Get client IP address.
     */
    public function getClientIp(): string
    {
        $ips = $this->getClientIps();
        return $ips[0] ?? '0.0.0.0';
    }

    /**
     * Get binding context for logging.
     */
    public function getBindingContext(): array
    {
        return [
            'provider' => $this->getProvider(),
            'user_id' => $this->getUserId(),
            'provider_user_id' => $this->input('provider_user_id'),
            'has_refresh_token' => $this->has('refresh_token'),
            'client_ip' => $this->getClientIp(),
            'timestamp' => time(),
        ];
    }

    /**
     * Validate that the user can bind this provider.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $provider = $this->getProvider();
            $userId = $this->getUserId();
            $providerUserId = $this->input('provider_user_id');

            // Custom validation logic can be added here
            // For example, checking if binding already exists
        });
    }
}
