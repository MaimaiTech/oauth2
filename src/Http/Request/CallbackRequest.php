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
use Hyperf\Context\Context;
use Hyperf\Swagger\Annotation\Property;
use Hyperf\Swagger\Annotation\Schema;
use Hyperf\Validation\Request\FormRequest;
use Mine\Support\Request\ClientIpRequestTrait;
use Mine\Support\Request\ClientOsTrait;
use Plugin\MaimaiTech\OAuth2\Service\OAuthClientFactory;

#[Schema(
    title: 'OAuth回调请求',
    description: 'OAuth授权回调处理请求参数',
    properties: [
        new Property('provider', description: 'OAuth提供者名称', type: 'string'),
        new Property('code', description: '授权码', type: 'string'),
        new Property('state', description: '状态参数(CSRF保护)', type: 'string'),
        new Property('error', description: '错误代码', type: 'string'),
        new Property('error_description', description: '错误描述', type: 'string'),
    ]
)]
class CallbackRequest extends FormRequest
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
            'code' => 'required_without:error|string|max:500',
            'state' => 'required|string|max:255',
            'error' => 'nullable|string|max:100',
            'error_description' => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'provider' => 'OAuth提供者',
            'code' => '授权码',
            'state' => '状态参数',
            'error' => '错误代码',
            'error_description' => '错误描述',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'OAuth提供者不能为空',
            'provider.in' => '不支持的OAuth提供者',
            'code.required_without' => '授权码不能为空',
            'state.required' => '状态参数不能为空(CSRF保护)',
        ];
    }

    /**
     * Get the provider name from route or input.
     */
    public function getProvider(): string
    {
        // Try to get from route parameter first, then from input
        return $this->route('provider') ?? $this->input('provider');
    }

    /**
     * Get authorization code.
     */
    public function getCode(): string
    {
        return $this->input('code', '');
    }

    /**
     * Get state parameter.
     */
    public function getState(): string
    {
        return $this->input('state', '');
    }

    /**
     * Check if callback contains error.
     */
    public function hasError(): bool
    {
        return ! empty($this->input('error'));
    }

    /**
     * Get error information.
     */
    public function getError(): array
    {
        return [
            'error' => $this->input('error'),
            'error_description' => $this->input('error_description'),
        ];
    }

    /**
     * Get formatted error message.
     */
    public function getErrorMessage(): string
    {
        $error = $this->input('error', '');
        $description = $this->input('error_description', '');

        if (empty($error)) {
            return '';
        }

        return $description ? "{$error}: {$description}" : $error;
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

    /**
     * Get callback context for logging/debugging.
     */
    public function getCallbackContext(): array
    {
        return [
            'provider' => $this->getProvider(),
            'has_code' => ! empty($this->getCode()),
            'has_error' => $this->hasError(),
            'client_ip' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'timestamp' => time(),
        ];
    }

    /**
     * Get validation data with route parameters merged.
     */
    public function validationData(): array
    {
        $data = parent::validationData();
        // Merge provider from route parameter if not present in request data
        if (! isset($data['provider']) && $this->route('provider')) {
            $data['provider'] = $this->route('provider');
        }

        return $data;
    }
}
