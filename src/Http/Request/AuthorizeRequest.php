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
use Mine\Support\Request\ClientOsTrait;
use Plugin\MaimaiTech\OAuth2\Service\OAuthClientFactory;

#[Schema(
    title: 'OAuth授权请求',
    description: '发起OAuth授权流程的请求参数',
    properties: [
        new Property('provider', description: 'OAuth提供者名称 (dingtalk, github, gitee, feishu, wechat, qq)', type: 'string'),
        new Property('redirect_after_auth', description: '授权后跳转地址', type: 'string'),
        new Property('extra_payload', description: '额外载荷数据', type: 'object'),
    ]
)]
class AuthorizeRequest extends FormRequest
{
    use ClientIpRequestTrait;
    use ClientOsTrait;
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
            'redirect_after_auth' => 'nullable|url|max:500',
            'extra_payload' => 'nullable|array',
            'redirect_uri' => 'sometimes|url',
        ];
    }

    public function attributes(): array
    {
        return [
            'provider' => 'OAuth提供者',
            'redirect_after_auth' => '授权后跳转地址',
            'extra_payload' => '额外载荷数据',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => '请选择OAuth提供者',
            'provider.in' => '不支持的OAuth提供者，支持的提供者: ' . implode(', ', OAuthClientFactory::getAvailableProviders()),
            'redirect_after_auth.url' => '跳转地址格式不正确',
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
     * Get redirect URL after authentication.
     */
    public function getRedirectAfterAuth(): ?string
    {
        return $this->input('redirect_after_auth');
    }

    /**
     * Get extra payload for state parameter.
     */
    public function getExtraPayload(): array
    {
        return $this->input('extra_payload', []);
    }

    /**
     * Get payload data for state parameter.
     */
    public function getStatePayload(): array
    {
        $payload = [];

        if ($this->getRedirectAfterAuth()) {
            $payload['redirect_after_auth'] = $this->getRedirectAfterAuth();
        }

        $extraPayload = $this->getExtraPayload();
        if (! empty($extraPayload)) {
            $payload['extra'] = $extraPayload;
        }

        return $payload;
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
     * Get user agent string.
     */
    public function getUserAgent(): string
    {
        return $this->getHeaderLine('User-Agent') ?? '';
    }

    protected function validationData(): array
    {
        $data = parent::validationData();
        if (! isset($data['provider']) && $this->route('provider')) {
            $data['provider'] = $this->route('provider');
        }
        return $data;
    }
}
