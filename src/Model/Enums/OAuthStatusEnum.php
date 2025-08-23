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
enum OAuthStatusEnum: int
{
    use EnumConstantsTrait;

    #[Message('oauth2.status.normal')]
    case NORMAL = 1;

    #[Message('oauth2.status.disabled')]
    case DISABLED = 2;

    /**
     * Check if status is normal/active.
     */
    public function isNormal(): bool
    {
        return $this === self::NORMAL;
    }

    /**
     * Check if status is disabled.
     */
    public function isDisabled(): bool
    {
        return $this === self::DISABLED;
    }
}
