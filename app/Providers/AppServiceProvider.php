<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Question;
use App\Models\Tag;
use App\Models\TeacherAccessRequest;
use App\Models\Test;
use App\Models\TestQuestionStat;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerTelescope();
    }

    /**
     * Telescope is a dev dependency excluded from auto-discovery: load it only locally.
     */
    protected function registerTelescope(): void
    {
        if (! $this->app->environment('local') || ! class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            return;
        }

        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureMorphMap();
    }

    /**
     * Short aliases for every morphable model, so class names are never stored in
     * the database (and renaming a class can't orphan rows). Enforced: a model
     * without an alias throws instead of silently storing its class name.
     * Existing spatie rows were migrated to the aliases.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'category' => Category::class,
            'tag' => Tag::class,
            'question' => Question::class,
            'test' => Test::class,
            'group' => Group::class,
            'teacher_access_request' => TeacherAccessRequest::class,
            'attempt' => Attempt::class,
            'attempt_question' => AttemptQuestion::class,
            'attempt_answer' => AttemptAnswer::class,
            'attempt_event' => AttemptEvent::class,
            'certificate' => Certificate::class,
            'user_badge' => UserBadge::class,
            'xp_transaction' => XpTransaction::class,
            'audit_log' => AuditLog::class,
            'test_question_stat' => TestQuestionStat::class,
        ]);
    }

    /**
     * Policies are auto-discovered from App\Policies (Model → ModelPolicy).
     *
     * Deliberately NO Gate::before() "super admin" bypass: a global bypass would skip
     * ownership rules and business constraints (e.g. admins can't take exams). Admin
     * rights come only from explicit permissions (Role::permissions()) and from policies
     * that handle admin cases on purpose.
     */
    protected function configureAuthorization(): void
    {
        //
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $isProduction = $this->app->isProduction();

        Date::use(CarbonImmutable::class);

        // Lazy loading, silently discarded attributes and missing attributes throw outside production.
        // Model::unguard() must never be called: mass assignment stays guarded by #[Fillable].
        Model::shouldBeStrict(! $isProduction);

        DB::prohibitDestructiveCommands($isProduction);

        URL::forceHttps($isProduction);

        // Safety net: never render stack traces or environment details in production,
        // even if APP_DEBUG is misconfigured.
        if ($isProduction) {
            config(['app.debug' => false]);
        }

        // Used by registration, password reset and password change (PasswordValidationRules).
        Password::defaults(function () use ($isProduction): Password {
            $rule = Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return $isProduction ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Application rate limiters. Fortify's `login`, `two-factor` and `passkeys`
     * limiters live in FortifyServiceProvider.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('autosave', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('autosave|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('join-group', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('join-group|'.($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip()));

        // Starting an exam attempt (each start locks the test row).
        RateLimiter::for('start-attempt', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('start-attempt|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Anti-cheat event batches (the client sends one every 10 s).
        RateLimiter::for('attempt-events', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('attempt-events|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // A stricter daily cap per user on top (independent of the IP).
        RateLimiter::for('join-group-daily', fn (Request $request): Limit => Limit::perDay((int) config('braincheck.join_group_daily_limit'))
            ->by('join-group-daily|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('certificate-verify', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('certificate-verify|'.$request->ip()));

        RateLimiter::for('locale', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('locale|'.$request->ip()));

        RateLimiter::for('question-preview', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('question-preview|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('teacher-request', fn (Request $request): Limit => Limit::perDay(3)
            ->by('teacher-request|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
