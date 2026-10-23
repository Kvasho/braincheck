<?php

declare(strict_types=1);

use App\Domain\Shared\LocalizedName;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use App\Support\CategoryTree;
use Inertia\Testing\AssertableInertia as Assert;

function categoryAdmin(): User
{
    $admin = User::factory()->admin()->withTwoFactor()->create();
    test()->actingAs($admin);

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function categoryPayload(array $overrides = []): array
{
    return [
        'name' => ['ka' => 'მათემატიკა', 'en' => 'Mathematics'],
        'slug' => null,
        'parent' => null,
        ...$overrides,
    ];
}

function category(string $en, ?Category $parent = null, int $sortOrder = 0): Category
{
    return Category::factory()->create([
        'name' => new LocalizedName("{$en} (ka)", $en),
        'slug' => str($en)->slug()->toString(),
        'parent_id' => $parent?->id,
        'sort_order' => $sortOrder,
    ]);
}

describe('creating and editing', function (): void {
    it('creates a category with an auto-generated slug and audits it', function (): void {
        $admin = categoryAdmin();

        $this->post(route('admin.categories.store'), categoryPayload(['name' => ['ka' => 'წრფივი ალგებრა', 'en' => 'Linear Algebra']]))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $category = Category::query()->sole();
        expect($category->slug)->toBe('linear-algebra')
            ->and($category->name->ka)->toBe('წრფივი ალგებრა')
            ->and($category->parent_id)->toBeNull();

        $log = AuditLog::query()->where('action', AuditAction::CategoryCreated->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->subject_id)->toBe($category->id);
    });

    it('makes generated slugs unique', function (): void {
        categoryAdmin();
        category('Mathematics');

        $this->post(route('admin.categories.store'), categoryPayload())->assertSessionHasNoErrors();

        expect(Category::query()->pluck('slug')->sort()->values()->all())->toBe(['mathematics', 'mathematics-2']);
    });

    it('requires both locale names', function (array $name, string $error): void {
        categoryAdmin();

        $this->post(route('admin.categories.store'), categoryPayload(['name' => $name]))->assertSessionHasErrors($error);

        expect(Category::query()->count())->toBe(0);
    })->with([
        'no Georgian' => [['ka' => '', 'en' => 'Maths'], 'name.ka'],
        'no English' => [['ka' => 'მათემატიკა', 'en' => ''], 'name.en'],
        'missing key' => [['en' => 'Maths'], 'name.ka'],
    ]);

    it('validates the slug format', function (string $slug): void {
        categoryAdmin();

        $this->post(route('admin.categories.store'), categoryPayload(['slug' => $slug]))->assertSessionHasErrors('slug');
    })->with(['Has Spaces', 'UPPER', 'trailing-', '-leading', 'double--hyphen', 'ünïcode', '<script>']);

    it('rejects a duplicate slug but lets a category keep its own', function (): void {
        categoryAdmin();
        $existing = category('Physics');
        $other = category('Biology');

        $this->post(route('admin.categories.store'), categoryPayload(['slug' => 'physics']))->assertSessionHasErrors('slug');
        $this->put(route('admin.categories.update', $other), categoryPayload(['slug' => 'physics']))->assertSessionHasErrors('slug');
        $this->put(route('admin.categories.update', $existing), categoryPayload(['slug' => 'physics']))->assertSessionHasNoErrors();
    });

    it('nests a category under a top-level parent', function (): void {
        categoryAdmin();
        $parent = category('Science');

        $this->post(route('admin.categories.store'), categoryPayload(['parent' => $parent->ulid]))->assertSessionHasNoErrors();

        expect(Category::query()->where('slug', 'mathematics')->sole()->parent_id)->toBe($parent->id);
    });

    it('does not let a child have children', function (): void {
        categoryAdmin();
        $child = category('Algebra', category('Mathematics'));

        $this->post(route('admin.categories.store'), categoryPayload(['name' => ['ka' => 'ა', 'en' => 'Deep'], 'parent' => $child->ulid]))
            ->assertSessionHasErrors('parent');
    });

    it('does not let a category with children become a child', function (): void {
        categoryAdmin();
        $parent = category('Mathematics');
        category('Algebra', $parent);
        $other = category('Science');

        $this->put(route('admin.categories.update', $parent), categoryPayload(['parent' => $other->ulid]))
            ->assertSessionHasErrors('parent');

        expect($parent->fresh()?->parent_id)->toBeNull();
    });

    it('does not let a category be its own parent', function (): void {
        categoryAdmin();
        $category = category('Mathematics');

        $this->put(route('admin.categories.update', $category), categoryPayload(['parent' => $category->ulid]))
            ->assertSessionHasErrors('parent');
    });

    it('rejects an unknown parent', function (): void {
        categoryAdmin();

        $this->post(route('admin.categories.store'), categoryPayload(['parent' => (string) str()->ulid()]))->assertSessionHasErrors('parent');
        $this->post(route('admin.categories.store'), categoryPayload(['parent' => '1']))->assertSessionHasErrors('parent');
    });

    it('updates names, slug and parent and audits the change', function (): void {
        $admin = categoryAdmin();
        $parent = category('Science');
        $category = category('Physics');

        $this->put(route('admin.categories.update', $category), categoryPayload([
            'name' => ['ka' => 'ფიზიკა', 'en' => 'Physics'],
            'slug' => 'physics-101',
            'parent' => $parent->ulid,
        ]))->assertSessionHasNoErrors();

        $category->refresh();
        expect($category->slug)->toBe('physics-101')
            ->and($category->parent_id)->toBe($parent->id);

        $log = AuditLog::query()->where('action', AuditAction::CategoryUpdated->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->properties['to'])->toBe(['name' => ['ka' => 'ფიზიკა', 'en' => 'Physics'], 'slug' => 'physics-101', 'parent' => $parent->ulid])
            ->and($log->properties['from']['slug'])->toBe('physics');
    });
});

describe('deleting', function (): void {
    it('is blocked while the category has children', function (): void {
        categoryAdmin();
        $parent = category('Mathematics');
        category('Algebra', $parent);

        $this->delete(route('admin.categories.destroy', $parent))->assertSessionHasErrors('category');

        expect(Category::query()->count())->toBe(2)
            ->and(AuditLog::query()->where('action', AuditAction::CategoryDeleted->value)->count())->toBe(0);
    });

    it('detaches questions and tests (SET NULL), including archived ones, and audits the counts', function (): void {
        $admin = categoryAdmin();
        $category = category('History');
        $question = Question::factory()->create(['category_id' => $category->id]);
        $archived = Question::factory()->create(['category_id' => $category->id]);
        $archived->delete();
        $test = Test::factory()->create(['category_id' => $category->id]);

        $this->delete(route('admin.categories.destroy', $category))->assertSessionHasNoErrors();

        expect(Category::query()->count())->toBe(0)
            ->and($question->fresh()?->category_id)->toBeNull()
            ->and(Question::withTrashed()->find($archived->id)?->category_id)->toBeNull()
            ->and($test->fresh()?->category_id)->toBeNull();

        $log = AuditLog::query()->where('action', AuditAction::CategoryDeleted->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->properties)->toMatchArray(['slug' => 'history', 'questions_detached' => 2, 'tests_detached' => 1]);
    });
});

describe('reordering', function (): void {
    it('reorders one level', function (): void {
        categoryAdmin();
        $a = category('Alpha', sortOrder: 0);
        $b = category('Beta', sortOrder: 1);
        $c = category('Gamma', sortOrder: 2);

        $this->put(route('admin.categories.reorder'), ['parent' => null, 'order' => [$c->ulid, $a->ulid, $b->ulid]])
            ->assertSessionHasNoErrors();

        expect([$c->fresh()?->sort_order, $a->fresh()?->sort_order, $b->fresh()?->sort_order])->toBe([0, 1, 2])
            ->and(AuditLog::query()->where('action', AuditAction::CategoryUpdated->value)->count())->toBe(1);
    });

    it('reorders the children of one parent', function (): void {
        categoryAdmin();
        $parent = category('Mathematics');
        $x = category('Algebra', $parent, 0);
        $y = category('Geometry', $parent, 1);

        $this->put(route('admin.categories.reorder'), ['parent' => $parent->ulid, 'order' => [$y->ulid, $x->ulid]])
            ->assertSessionHasNoErrors();

        expect($y->fresh()?->sort_order)->toBe(0)->and($x->fresh()?->sort_order)->toBe(1);
    });

    it('rejects an invalid payload without applying anything', function (Closure $payload): void {
        categoryAdmin();
        $parent = category('Mathematics', sortOrder: 0);
        $child = category('Algebra', $parent, 0);
        $second = category('Science', sortOrder: 1);

        $this->put(route('admin.categories.reorder'), $payload($parent, $child, $second))->assertSessionHasErrors();

        expect($parent->fresh()?->sort_order)->toBe(0)
            ->and($second->fresh()?->sort_order)->toBe(1)
            ->and($child->fresh()?->parent_id)->toBe($parent->id);
    })->with([
        'foreign ULID' => fn (Category $parent, Category $child, Category $second): array => ['order' => [$second->ulid, (string) str()->ulid()]],
        'cross-level move' => fn (Category $parent, Category $child, Category $second): array => ['order' => [$second->ulid, $parent->ulid, $child->ulid]],
        'child into the top level' => fn (Category $parent, Category $child, Category $second): array => ['order' => [$child->ulid, $second->ulid]],
        'missing sibling' => fn (Category $parent, Category $child, Category $second): array => ['order' => [$second->ulid]],
        'duplicate' => fn (Category $parent, Category $child, Category $second): array => ['order' => [$second->ulid, $second->ulid, $parent->ulid]],
        'not a ULID' => fn (Category $parent, Category $child, Category $second): array => ['order' => ['1', '2']],
        'unknown parent' => fn (Category $parent, Category $child, Category $second): array => ['parent' => (string) str()->ulid(), 'order' => [$child->ulid]],
        'empty' => fn (Category $parent, Category $child, Category $second): array => ['order' => []],
    ]);
});

describe('tree cache', function (): void {
    it('is invalidated by every change', function (): void {
        $tree = app(CategoryTree::class);
        categoryAdmin();

        expect($tree->get())->toBe([]);

        $this->post(route('admin.categories.store'), categoryPayload())->assertSessionHasNoErrors();
        expect($tree->get())->toHaveCount(1)
            ->and($tree->get()[0]['slug'])->toBe('mathematics');

        $category = Category::query()->sole();
        $this->put(route('admin.categories.update', $category), categoryPayload(['slug' => 'maths']))->assertSessionHasNoErrors();
        expect($tree->get()[0]['slug'])->toBe('maths');

        $this->post(route('admin.categories.store'), categoryPayload(['name' => ['ka' => 'ალგებრა', 'en' => 'Algebra'], 'parent' => $category->ulid]));
        expect($tree->get()[0]['children'])->toHaveCount(1);

        $second = category('Science', sortOrder: 5);
        $tree->forget();
        $this->put(route('admin.categories.reorder'), ['order' => [$second->ulid, $category->ulid]])->assertSessionHasNoErrors();
        expect($tree->get()[0]['slug'])->toBe('science');

        $this->delete(route('admin.categories.destroy', $second))->assertSessionHasNoErrors();
        expect($tree->get())->toHaveCount(1);
    });

    it('caches plain arrays, not models', function (): void {
        category('Mathematics');

        app(CategoryTree::class)->get();

        expect(cache()->get(CategoryTree::CACHE_KEY))->toBe([
            ['ulid' => Category::query()->sole()->ulid, 'name' => ['ka' => 'Mathematics (ka)', 'en' => 'Mathematics'], 'slug' => 'mathematics', 'children' => []],
        ]);
    });
});

it('renders the tree with usage counts', function (): void {
    categoryAdmin();
    $parent = category('Mathematics');
    $child = category('Algebra', $parent);
    Question::factory()->count(2)->create(['category_id' => $child->id]);

    $this->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/categories/index')
            ->has('tree', 1)
            ->where('tree.0.ulid', $parent->ulid)
            ->where('tree.0.children.0.ulid', $child->ulid)
            ->where('tree.0.children.0.questions_count', 2)
            ->missing('tree.0.id'));
});
