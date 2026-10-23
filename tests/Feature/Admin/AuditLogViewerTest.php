<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function auditViewer(): User
{
    $admin = User::factory()->admin()->withTwoFactor()->create(['name' => 'Viewer Admin']);
    test()->actingAs($admin);

    return $admin;
}

/**
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function auditActionsFor(array $query): array
{
    $props = test()->get(route('admin.audit-log.index', $query))->assertOk()->viewData('page')['props'];

    return collect($props['entries']['data'])->pluck('action')->all();
}

it('lists entries newest first with labels, actor and subject', function (): void {
    auditViewer();
    $nino = User::factory()->student()->create(['name' => 'Nino']);
    AuditLog::factory()->for($nino, 'actor')->about($nino)->create(['action' => AuditAction::Login->value]);
    AuditLog::factory()->for($nino, 'actor')->create(['action' => AuditAction::Logout->value]);

    $this->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-log/index')
            ->has('entries.data', 2)
            ->where('entries.data.0.action', 'auth.logout')
            ->where('entries.data.0.action_label', __('Signed out'))
            ->where('entries.data.1.actor.name', 'Nino')
            ->where('entries.data.1.subject.type', 'user')
            ->where('entries.data.1.subject.ulid', $nino->ulid)
            ->missing('entries.data.0.id')
            ->has('actions', count(AuditAction::cases())));
});

it('filters by action (multiple), actor, date range and subject type', function (): void {
    auditViewer();
    $nino = User::factory()->student()->create(['name' => 'Nino Beridze', 'email' => 'nino@example.com']);
    $luka = User::factory()->student()->create(['name' => 'Luka']);
    $tag = Tag::factory()->create();

    AuditLog::factory()->for($nino, 'actor')->create(['action' => AuditAction::Login->value, 'created_at' => now()->subDays(10)]);
    AuditLog::factory()->for($nino, 'actor')->create(['action' => AuditAction::Logout->value, 'created_at' => now()->subDays(2)]);
    AuditLog::factory()->for($luka, 'actor')->about($tag)->create(['action' => AuditAction::TagCreated->value, 'created_at' => now()]);

    expect(auditActionsFor(['action' => ['auth.login', 'auth.logout']]))->toEqualCanonicalizing(['auth.login', 'auth.logout'])
        ->and(auditActionsFor(['actor' => 'nino@']))->toEqualCanonicalizing(['auth.login', 'auth.logout'])
        ->and(auditActionsFor(['actor' => 'Beri']))->toHaveCount(2)
        ->and(auditActionsFor(['from' => now()->subDays(3)->toDateString(), 'to' => now()->subDay()->toDateString()]))->toBe(['auth.logout'])
        ->and(auditActionsFor(['from' => now()->toDateString()]))->toBe(['tag.created'])
        ->and(auditActionsFor(['subject_type' => 'tag']))->toBe(['tag.created']);
});

it('escapes LIKE wildcards in the actor search', function (): void {
    auditViewer();
    AuditLog::factory()->for(User::factory()->create(['name' => 'Plain']), 'actor')->create();

    expect(auditActionsFor(['actor' => '%']))->toBe([]);
});

it('validates filters', function (array $query, string $error): void {
    auditViewer();

    $this->get(route('admin.audit-log.index', $query))->assertSessionHasErrors($error);
})->with([
    'unknown action' => [['action' => ['auth.hacked']], 'action.0'],
    'bad date' => [['from' => '22-10-2026'], 'from'],
    'inverted range' => [['from' => '2026-10-22', 'to' => '2026-10-01'], 'to'],
    'class name as subject type' => [['subject_type' => 'App\\Models\\User'], 'subject_type'],
]);

it('is forbidden to teachers and students', function (string $factoryState): void {
    $user = User::factory()->{$factoryState}()->withTwoFactor()->create();

    $this->actingAs($user)->get(route('admin.audit-log.index'))->assertForbidden();
})->with(['teacher', 'student']);

it('has no route that can update or delete entries', function (): void {
    $writable = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_contains($route->uri(), 'audit')
            && array_diff($route->methods(), ['GET', 'HEAD']) !== [])
        ->map(fn (RoutingRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($writable)->toBe([]);

    $admin = auditViewer();
    $log = AuditLog::factory()->for($admin, 'actor')->create();

    foreach (['put', 'patch', 'delete', 'post'] as $method) {
        $this->{$method}('/admin/audit-log')->assertStatus(405);
    }

    expect($log->fresh())->not->toBeNull();
});

it('returns hostile property values as plain text, never as markup', function (): void {
    auditViewer();
    AuditLog::factory()->create([
        'action' => AuditAction::UserBlocked->value,
        'properties' => ['reason' => '<script>alert("xss")</script>', 'nested' => ['html' => '<img src=x onerror=alert(1)>']],
    ]);

    $response = $this->get(route('admin.audit-log.index'))->assertOk();
    $properties = $response->viewData('page')['props']['entries']['data'][0]['properties'];

    expect($properties['reason'])->toBe('<script>alert("xss")</script>')
        ->and($properties['nested']['html'])->toBe('<img src=x onerror=alert(1)>')
        ->and((string) $response->getContent())->not->toContain('<script>alert("xss")</script>')
        ->and((string) $response->getContent())->not->toContain('<img src=x onerror');
});
