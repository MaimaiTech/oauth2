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

namespace Plugin\MaimaiTech\OAuth2\Model\Enums;

use Hyperf\Constants\Annotation\Constants;
use Hyperf\Constants\Annotation\Message;
use Hyperf\Constants\EnumConstantsTrait;

#[Constants]
enum OAuthStateStatusEnum: int
{
    use EnumConstantsTrait;

    #[Message('oauth2.state.valid')]
    case VALID = 1;

    #[Message('oauth2.state.used')]
    case USED = 2;

    #[Message('oauth2.state.expired')]
    case EXPIRED = 3;

    /**
     * Check if state is valid and can be used.
     */
    public function isValid(): bool
    {
        return $this === self::VALID;
    }

    /**
     * Check if state has been used.
     */
    public function isUsed(): bool
    {
        return $this === self::USED;
    }

    /**
     * Check if state has expired.
     */
    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }
}
