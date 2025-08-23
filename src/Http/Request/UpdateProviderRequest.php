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
use Hyperf\Swagger\Annotation\Items;
use Hyperf\Swagger\Annotation\Property;
use Hyperf\Swagger\Annotation\Schema;
use Hyperf\Validation\Request\FormRequest;

#[Schema(
    title: '更新OAuth提供者',
    description: '更新OAuth提供者配置请求参数',
    properties: [
        new Property('display_name', description: '显示名称', type: 'string'),
        new Property('client_id', description: '客户端ID', type: 'string'),
        new Property('client_secret', description: '客户端密钥', type: 'string'),
        new Property('redirect_uri', description: '回调地址', type: 'string'),
        new Property('scopes', description: '权限范围', type: 'array', items: new Items(type: 'string')),
        new Property('extra_config', description: '额外配置', type: 'object'),
        new Property('enabled', description: '是否启用', type: 'boolean'),
        new Property('status', description: '状态值 (0=禁用, 1=启用)', type: 'integer', maximum: 1, minimum: 0),
        new Property('sort', description: '排序号', type: 'integer'),
    ]
)]
class UpdateProviderRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        $providerId = $this->route('id');

        return [
            'display_name' => 'sometimes|string|max:50',
            'client_id' => 'sometimes|string|max:255',
            'client_secret' => 'sometimes|string|max:500',
            'redirect_uri' => 'sometimes|url|max:500',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:100',
            'extra_config' => 'nullable|array',
            'enabled' => 'boolean',
            'status' => 'integer|in:0,1',
            'sort' => 'integer|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'display_name' => '显示名称',
            'client_id' => '客户端ID',
            'client_secret' => '客户端密钥',
            'redirect_uri' => '回调地址',
            'scopes' => '权限范围',
            'extra_config' => '额外配置',
            'enabled' => '启用状态',
            'status' => '状态',
            'sort' => '排序',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => '客户端ID不能为空',
            'client_secret.required' => '客户端密钥不能为空',
            'redirect_uri.url' => '回调地址格式不正确',
        ];
    }

    /**
     * Get validated data for update.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        // Remove null values to prevent overwriting with null
        return array_filter($validated, static function ($value) {
            return $value !== null;
        });
    }

    /**
     * Check if client configuration fields are being updated.
     */
    public function isUpdatingClientConfig(): bool
    {
        $clientConfigFields = ['client_id', 'client_secret', 'redirect_uri', 'scopes'];

        foreach ($clientConfigFields as $field) {
            if ($this->has($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if enabling the provider.
     */
    public function isEnabling(): bool
    {
        return $this->boolean('enabled', false) === true;
    }

    /**
     * Get client configuration data only.
     */
    public function getClientConfigData(): array
    {
        $clientFields = ['client_id', 'client_secret', 'redirect_uri', 'scopes'];

        return array_intersect_key($this->validated(), array_flip($clientFields));
    }
}
