<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class TeacherRequestNotAllowed extends DomainException
{
    public static function alreadyReviewed(): self
    {
        return new self(__('This request has already been reviewed.'));
    }

    public static function requesterIsNotAStudent(): self
    {
        return new self(__('This user is no longer a student, so the request can only be rejected.'));
    }
}
