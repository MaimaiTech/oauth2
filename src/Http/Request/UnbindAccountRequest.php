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
    title: '解绑OAuth账户',
    description: '解除OAuth账户绑定的请求参数',
    properties: [
        new Property('provider', description: 'OAuth提供者名称 (dingtalk, github, gitee, feishu, wechat, qq)', type: 'string'),
        new Property('user_id', description: '用户ID', type: 'integer'),
        new Property('confirm', description: '确认解绑 (必须为 true)', type: 'boolean'),
        new Property('reason', description: '解绑原因', type: 'string'),
    ]
)]
class UnbindAccountRequest extends FormRequest
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
            'confirm' => 'required|boolean|accepted',
            'reason' => 'nullable|string|max:200',
        ];
    }

    public function attributes(): array
    {
        return [
            'provider' => 'OAuth提供者',
            'user_id' => '用户ID',
            'confirm' => '确认解绑',
            'reason' => '解绑原因',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => '请选择OAuth提供者',
            'provider.in' => '不支持的OAuth提供者',
            'user_id.required' => '用户ID不能为空',
            'user_id.exists' => '用户不存在',
            'confirm.required' => '请确认解绑操作',
            'confirm.accepted' => '必须确认解绑才能执行操作',
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
     * Get the unbind reason.
     */
    public function getReason(): ?string
    {
        return $this->input('reason');
    }

    /**
     * Check if user confirmed the unbind operation.
     */
    public function isConfirmed(): bool
    {
        return (bool) $this->input('confirm');
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
     * Get unbind context for logging.
     */
    public function getUnbindContext(): array
    {
        return [
            'provider' => $this->getProvider(),
            'user_id' => $this->getUserId(),
            'reason' => $this->getReason(),
            'client_ip' => $this->getClientIp(),
            'timestamp' => time(),
        ];
    }

    /**
     * Alternative validation for route-based provider.
     */
    public function getProviderFromRoute(): string
    {
        return $this->route('provider') ?? $this->getProvider();
    }

    /**
     * Custom validation after basic rules.
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Add custom validation logic here
            // For example, check if the binding exists
            $provider = $this->getProvider();
            $userId = $this->getUserId();

            // You could add repository check here to verify binding exists
            // This would require injecting the repository, but for now
            // we'll leave this as a placeholder for future enhancement
        });
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
