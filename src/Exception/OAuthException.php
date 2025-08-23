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

namespace Plugin\MaimaiTech\OAuth2\Exception;

use Exception;

/**
 * OAuth2 specific exception class.
 */
class OAuthException extends \Exception
{
    protected string $provider = '';

    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, string $provider = '', array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->provider = $provider;
        $this->context = $context;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Create OAuth configuration exception.
     */
    public static function configurationError(string $message, string $provider = ''): self
    {
        return new self($message, 1001, null, $provider);
    }

    /**
     * Create OAuth flow exception.
     */
    public static function flowError(string $message, string $provider = '', array $context = []): self
    {
        return new self($message, 1002, null, $provider, $context);
    }

    /**
     * Create OAuth API exception.
     */
    public static function apiError(string $message, int $statusCode, string $provider = '', array $context = []): self
    {
        return new self($message, $statusCode, null, $provider, $context);
    }

    /**
     * Create OAuth token exception.
     */
    public static function tokenError(string $message, string $provider = ''): self
    {
        return new self($message, 1003, null, $provider);
    }

    /**
     * Create OAuth state validation exception.
     */
    public static function stateError(string $message, string $provider = '', array $context = []): self
    {
        return new self($message, 1004, null, $provider, $context);
    }

    /**
     * Create OAuth rate limit exception.
     */
    public static function rateLimitError(string $message, string $provider = ''): self
    {
        return new self($message, 1005, null, $provider);
    }
}
