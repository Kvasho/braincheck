<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class RoleChangeNotAllowed extends DomainException
{
    public static function cannotDemoteSelf(): self
    {
        return new self(__('You cannot remove your own administrator role.'));
    }

    public static function lastAdmin(): self
    {
        return new self(__('The last administrator cannot be demoted.'));
    }

    public static function alreadyHasRole(): self
    {
        return new self(__('This user already has a role.'));
    }
}
