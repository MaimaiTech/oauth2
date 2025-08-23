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
use Plugin\MaimaiTech\OAuth2\Service\OAuthClientFactory;

#[Schema(
    title: '创建OAuth提供者',
    description: '创建OAuth提供者配置请求参数',
    properties: [
        new Property('name', description: 'OAuth提供者名称 (dingtalk, github, gitee, feishu, wechat, qq)', type: 'string'),
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
class CreateProviderRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        $supportedProviders = implode(',', OAuthClientFactory::getAvailableProviders());

        return [
            'name' => [
                'required',
                'string',
                "in:{$supportedProviders}",
                'unique:oauth_providers,name',
            ],
            'display_name' => 'required|string|max:50',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string|max:500',
            'redirect_uri' => 'required|url|max:500',
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
            'name' => 'OAuth提供者名称',
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
            'name.in' => '不支持的OAuth提供者，支持的提供者: ' . implode(', ', OAuthClientFactory::getAvailableProviders()),
            'name.unique' => '该OAuth提供者已存在',
            'client_id.required' => '客户端ID不能为空',
            'client_secret.required' => '客户端密钥不能为空',
            'redirect_uri.required' => '回调地址不能为空',
            'redirect_uri.url' => '回调地址格式不正确',
        ];
    }

    /**
     * Get validated data with default values.
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();

        // Set default values
        $validated['enabled'] ??= false;
        $validated['status'] ??= 1;
        $validated['sort'] ??= 0;
        $validated['scopes'] ??= $this->getDefaultScopes($validated['name']);

        return $validated;
    }

    /**
     * Get default scopes for the provider.
     */
    private function getDefaultScopes(string $providerName): array
    {
        $providerInfo = OAuthClientFactory::getProviderInfo($providerName);
        return $providerInfo['default_scopes'] ?? [];
    }
}
