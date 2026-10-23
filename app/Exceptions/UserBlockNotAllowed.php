<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class UserBlockNotAllowed extends DomainException
{
    public static function cannotBlockSelf(): self
    {
        return new self(__('You cannot block your own account.'));
    }

    public static function lastAdmin(): self
    {
        return new self(__('The last active administrator cannot be blocked.'));
    }
}
