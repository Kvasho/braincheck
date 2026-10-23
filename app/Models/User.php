<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use LogicException;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Locale|null $locale
 * @property Carbon|null $blocked_at
 * @property string|null $blocked_reason
 * @property int $xp_total
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUlid, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Defaults for columns not set on creation, so freshly created models
     * expose them too (strict mode forbids reading missing attributes).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => null,
        'blocked_at' => null,
        'blocked_reason' => null,
        'xp_total' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locale' => Locale::class,
            'blocked_at' => 'datetime',
            'xp_total' => 'integer',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Locale for notifications and mail (verification, password reset).
     */
    public function preferredLocale(): ?string
    {
        return $this->locale?->value;
    }

    /**
     * The user's single role. For dashboard selection and display only:
     * authorization checks permissions and policies, never the role.
     */
    public function role(): Role
    {
        return $this->roleOrNull()
            ?? throw new LogicException("User [{$this->id}] has no role.");
    }

    /**
     * The single role, or null for a user that has not been assigned one yet.
     */
    public function roleOrNull(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return is_string($name) ? Role::tryFrom($name) : null;
    }

    public function isAdmin(): bool
    {
        return $this->roleOrNull() === Role::Admin;
    }

    public function isTeacher(): bool
    {
        return $this->roleOrNull() === Role::Teacher;
    }

    public function isStudent(): bool
    {
        return $this->roleOrNull() === Role::Student;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Two-factor authentication is set up *and* confirmed with a valid code.
     * Independent of Fortify's `confirm` option, so it never weakens silently.
     */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Questions this user owns (teacher).
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'owner_id');
    }

    /**
     * Tests this user owns (teacher).
     *
     * @return HasMany<Test, $this>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class, 'owner_id');
    }

    /**
     * Groups this user owns (teacher).
     *
     * @return HasMany<Group, $this>
     */
    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /**
     * Groups this user is a member of (student).
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('joined_at');
    }

    /**
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<UserBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /**
     * @return HasMany<XpTransaction, $this>
     */
    public function xpTransactions(): HasMany
    {
        return $this->hasMany(XpTransaction::class);
    }

    /**
     * @return HasMany<TeacherAccessRequest, $this>
     */
    public function teacherAccessRequests(): HasMany
    {
        return $this->hasMany(TeacherAccessRequest::class);
    }
}
