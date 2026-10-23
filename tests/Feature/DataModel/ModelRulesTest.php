<?php

declare(strict_types=1);

use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

describe('secrets never serialize', function (): void {
    it('keeps a question definition and id out of toArray / toJson', function (): void {
        $question = Question::factory()->singleChoice()->create()->fresh();
        $correct = $question?->definition->toArray()['correct'];

        expect($question?->toArray())->not->toHaveKeys(['definition', 'id'])
            ->and($question?->toJson())->not->toContain('"definition"')->not->toContain((string) $correct);
    });

    it('keeps an attempt question snapshot out of toArray / toJson', function (): void {
        $attemptQuestion = Attempt::factory()->create()->questions()->firstOrFail();

        expect($attemptQuestion->toArray())->not->toHaveKeys(['snapshot', 'id'])
            ->and($attemptQuestion->toJson())->not->toContain('"snapshot"')->not->toContain('"definition"');
    });

    it('keeps a group invite code out of toArray / toJson', function (): void {
        $group = Group::factory()->create();

        expect($group->toArray())->not->toHaveKeys(['invite_code', 'id'])
            ->and($group->toJson())->not->toContain($group->invite_code);
    });

    it('keeps a certificate internal id out of toArray / toJson', function (): void {
        $certificate = Certificate::factory()->create();

        expect($certificate->toArray())->not->toHaveKey('id')
            ->and($certificate->toArray())->toHaveKey('ulid');
    });
});

describe('route keys', function (): void {
    /**
     * @return array<string, array{class-string<Model>, Closure(): Model}>
     */
    $models = [
        'category' => [Category::class, fn (): Model => Category::factory()->create()],
        'question' => [Question::class, fn (): Model => Question::factory()->create()],
        'test' => [Test::class, fn (): Model => Test::factory()->create()],
        'group' => [Group::class, fn (): Model => Group::factory()->create()],
        'attempt' => [Attempt::class, fn (): Model => Attempt::factory()->create()],
        'certificate' => [Certificate::class, fn (): Model => Certificate::factory()->create()],
    ];

    it('resolves models by ulid and returns 404 for numeric ids', function (string $class, Closure $make): void {
        Route::middleware('web')->get('/__route-key-probe/{model}', fn (Model $model): string => (string) $model->getRouteKey())
            ->whereAlphaNumeric('model');
        Route::bind('model', fn (string $value): Model => (new $class)->resolveRouteBinding($value) ?? abort(404));

        /** @var Model $model */
        $model = $make();

        $this->get("/__route-key-probe/{$model->getRouteKey()}")->assertOk()->assertSee((string) $model->getAttribute('ulid'));
        $this->get("/__route-key-probe/{$model->getKey()}")->assertNotFound();
        $this->get('/__route-key-probe/'.strtolower((string) Str::ulid()))->assertNotFound();
    })->with($models);

    it('uses the ulid as the route key name', function (string $class): void {
        expect((new $class)->getRouteKeyName())->toBe('ulid');
    })->with(array_map(fn (array $entry): array => [$entry[0]], $models));
});

describe('audit log', function (): void {
    it('is append-only', function (Closure $mutation): void {
        $log = AuditLog::factory()->create();

        expect(fn () => $mutation($log))->toThrow(LogicException::class);
        expect(AuditLog::query()->whereKey($log->id)->value('action'))->toBe('user.role_changed');
    })->with([
        'update' => fn (AuditLog $log) => $log->update(['action' => 'tampered']),
        'save' => fn (AuditLog $log) => $log->forceFill(['action' => 'tampered'])->save(),
        'delete' => fn (AuditLog $log) => $log->delete(),
        'mass update' => fn (AuditLog $log) => AuditLog::query()->whereKey($log->id)->update(['action' => 'tampered']),
        'mass delete' => fn (AuditLog $log) => AuditLog::query()->whereKey($log->id)->delete(),
    ]);
});

describe('morph map', function (): void {
    it('stores short aliases, never class names', function (): void {
        $attempt = Attempt::factory()->create();
        XpTransaction::factory()->forSource($attempt)->create();
        AuditLog::factory()->about($attempt->user()->firstOrFail())->create();

        expect(DB::table('xp_transactions')->value('source_type'))->toBe('attempt')
            ->and(DB::table('audit_logs')->value('subject_type'))->toBe('user')
            ->and(DB::table('model_has_roles')->distinct()->pluck('model_type')->all())->toBe(['user']);
    });

    it('maps every model to an alias', function (): void {
        $models = collect(glob(app_path('Models/*.php')) ?: [])
            ->map(fn (string $path): string => 'App\\Models\\'.pathinfo($path, PATHINFO_FILENAME))
            ->all();

        expect(array_values(Relation::morphMap()))->toEqualCanonicalizing($models)
            ->and(Relation::requiresMorphMap())->toBeTrue();

        foreach (Relation::morphMap() as $alias => $class) {
            expect((new $class)->getMorphClass())->toBe($alias)->not->toContain('\\');
        }
    });
});

describe('mass assignment', function (): void {
    it('runs in strict mode in tests', function (): void {
        expect(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
    });

    it('throws when protected fields are mass assigned', function (string $class, array $attributes): void {
        expect(fn () => new $class($attributes))->toThrow(MassAssignmentException::class);
    })->with([
        'question owner_id' => [Question::class, ['owner_id' => 1, 'body' => App\Domain\Shared\RichTextDocument::fromPlainText('x')]],
        'test owner_id' => [Test::class, ['owner_id' => 1, 'title' => 'x']],
        'test status' => [Test::class, ['status' => 'published', 'title' => 'x']],
        'attempt score' => [Attempt::class, ['score' => 100]],
        'attempt status' => [Attempt::class, ['status' => 'submitted']],
        'group invite_code' => [Group::class, ['invite_code' => 'ABCDEFGH23', 'name' => 'x']],
        'certificate verification_code' => [Certificate::class, ['verification_code' => str_repeat('a', 32)]],
        'user xp_total' => [User::class, ['xp_total' => 9999, 'name' => 'x']],
        'any ulid' => [Question::class, ['ulid' => strtolower((string) Str::ulid())]],
    ]);

    it('never writes protected fields through fill()', function (): void {
        $question = Question::factory()->create();
        $owner = $question->owner_id;

        try {
            $question->fill(['owner_id' => $owner + 1000]);
        } catch (MassAssignmentException) {
            // expected in strict mode
        }

        expect($question->fresh()?->owner_id)->toBe($owner);
    });
});

describe('attempt question snapshots', function (): void {
    it('keep history when the live question changes later', function (): void {
        $attempt = Attempt::factory()->create();
        $attemptQuestion = $attempt->questions()->firstOrFail();
        $before = $attemptQuestion->snapshot;

        $question = Question::query()->findOrFail($attemptQuestion->question_id);
        $question->forceFill(['body' => App\Domain\Shared\RichTextDocument::fromPlainText('Edited after the attempt started'), 'version' => $question->version + 1])->save();
        $question->delete();

        $after = AttemptQuestion::query()->findOrFail($attemptQuestion->id);

        expect($after->snapshot)->toBe($before)
            ->and($after->question)->not->toBeNull();
    });
});
