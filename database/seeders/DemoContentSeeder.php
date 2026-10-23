<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\MatchingDefinition;
use App\Domain\Questions\Definitions\MultipleChoiceDefinition;
use App\Domain\Questions\Definitions\NumericDefinition;
use App\Domain\Questions\Definitions\OrderingDefinition;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Domain\Shared\LocalizedName;
use App\Domain\Shared\RichTextDocument;
use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use App\Enums\ResultsVisibility;
use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Question;
use App\Models\Tag;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Realistic demo content for local development (NOT called from DatabaseSeeder).
 *
 *   php artisan db:seed --class=DemoUsersSeeder
 *   php artisan db:seed --class=DemoContentSeeder
 *
 * Creates bilingual categories, tags, ~60 questions (every type and difficulty),
 * 8 tests in different states, 3 groups with members and assignments, and graded
 * attempts with varied scores. Depends on the demo users (runs DemoUsersSeeder,
 * which is idempotent). Skips itself when the demo content already exists.
 */
class DemoContentSeeder extends Seeder
{
    /** Fine-grained tags in the question data → the ~15 tags we create. */
    private const TAG_GROUPS = [
        'arithmetic' => 'arithmetic', 'equations' => 'equations', 'number-theory' => 'number-theory',
        'angles' => 'shapes', 'area' => 'shapes', 'solids' => 'shapes',
        'middle-ages' => 'middle-ages', 'georgia' => 'georgia', 'literature' => 'literature',
        'modern-history' => 'world-history', 'antiquity' => 'world-history',
        'cells' => 'life-science', 'animals' => 'life-science', 'plants' => 'life-science', 'genetics' => 'life-science',
        'mountains' => 'geography-facts', 'capitals' => 'geography-facts', 'rivers' => 'geography-facts',
        'php' => 'php', 'laravel' => 'php', 'javascript' => 'javascript',
        'syntax' => 'fundamentals', 'types' => 'fundamentals', 'basics' => 'fundamentals', 'tools' => 'fundamentals', 'databases' => 'fundamentals',
        'http' => 'web', 'security' => 'web',
        'grammar' => 'grammar', 'present-simple' => 'grammar', 'conditionals' => 'grammar', 'present-perfect' => 'grammar',
        'vocabulary' => 'vocabulary',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoContentSeeder only runs in the local or testing environment: it creates demo data.',
            );
        }

        $this->call(DemoUsersSeeder::class);

        if (Category::query()->where('slug', 'mathematics')->exists()) {
            $this->command?->warn('Demo content already exists, skipping.');

            return;
        }

        DB::transaction(function (): void {
            $users = $this->demoUsers();
            $categories = $this->categories();
            $tags = $this->tags();
            $questions = $this->questions($users, $categories, $tags);
            $tests = $this->tests($users, $categories, $questions);
            $this->groups($users, $tests);
            $this->attempts($users, $tests);
        });
    }

    /**
     * @return array<string, User>
     */
    private function demoUsers(): array
    {
        $emails = ['admin', 'teacher1', 'teacher2', 'student1', 'student2', 'student3', 'student4', 'student5'];

        return collect($emails)
            ->mapWithKeys(fn (string $name): array => [$name => User::query()->where('email', "{$name}@braincheck.test")->firstOrFail()])
            ->all();
    }

    /**
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $tree = [
            ['mathematics', 'მათემატიკა', 'Mathematics', [['algebra', 'ალგებრა', 'Algebra'], ['geometry', 'გეომეტრია', 'Geometry']]],
            ['history', 'ისტორია', 'History', [['georgian-history', 'საქართველოს ისტორია', 'Georgian history'], ['world-history', 'მსოფლიო ისტორია', 'World history']]],
            ['programming', 'პროგრამირება', 'Programming', [['php', 'PHP', 'PHP'], ['javascript', 'JavaScript', 'JavaScript']]],
            ['biology', 'ბიოლოგია', 'Biology', []],
            ['geography', 'გეოგრაფია', 'Geography', []],
            ['english', 'ინგლისური', 'English', [['english-grammar', 'გრამატიკა', 'Grammar']]],
        ];

        $categories = [];

        foreach ($tree as $order => [$slug, $ka, $en, $children]) {
            $parent = Category::query()->create([
                'name' => new LocalizedName($ka, $en),
                'slug' => $slug,
                'sort_order' => $order,
            ]);
            $categories[$slug] = $parent;

            foreach ($children as $childOrder => [$childSlug, $childKa, $childEn]) {
                $categories[$childSlug] = Category::query()->create([
                    'parent_id' => $parent->id,
                    'name' => new LocalizedName($childKa, $childEn),
                    'slug' => $childSlug,
                    'sort_order' => $childOrder,
                ]);
            }
        }

        return $categories;
    }

    /**
     * @return array<string, Tag>
     */
    private function tags(): array
    {
        return collect(array_unique(array_values(self::TAG_GROUPS)))
            ->mapWithKeys(fn (string $name): array => [$name => Tag::query()->create(['name' => $name, 'slug' => Str::slug($name)])])
            ->all();
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Category>  $categories
     * @param  array<string, Tag>  $tags
     * @return array<string, list<Question>> questions per category slug
     */
    private function questions(array $users, array $categories, array $tags): array
    {
        /** @var list<array{string, string, string, string, string, string|null, array<string, mixed>, list<string>}> $data */
        $data = require __DIR__.'/data/demo-questions.php';
        $byCategory = [];

        foreach ($data as [$teacher, $categorySlug, $type, $difficulty, $body, $explanation, $spec, $questionTags]) {
            $questionType = QuestionType::from($type);

            $question = new Question([
                'category_id' => $categories[$categorySlug]->id,
                'type' => $questionType,
                'difficulty' => $difficulty,
                'body' => RichTextDocument::fromPlainText((string) $body),
                'explanation' => $explanation === null ? null : RichTextDocument::fromPlainText((string) $explanation),
                'definition' => $this->definition($questionType, $spec),
                'points' => $questionType === QuestionType::Text ? 3 : ($difficulty === 'hard' ? 2 : 1),
            ]);
            $question->owner_id = $users[$teacher]->id;
            $question->save();

            $tagIds = collect($questionTags)
                ->map(fn (string $tag): int => $tags[self::TAG_GROUPS[$tag]]->id)
                ->unique()
                ->values()
                ->all();
            $question->tags()->attach($tagIds);

            $byCategory[$categorySlug][] = $question;
        }

        return $byCategory;
    }

    /**
     * Turn the compact, human-written spec into a definition with random short ids.
     *
     * @param  array<string, mixed>  $spec
     */
    private function definition(QuestionType $type, array $spec): QuestionDefinition
    {
        $items = fn (array $texts): array => array_values(array_map(fn (mixed $text): Item => Item::make((string) $text), $texts));

        switch ($type) {
            case QuestionType::SingleChoice:
                $options = $items((array) $spec['options']);

                return new SingleChoiceDefinition($options, $options[(int) $spec['correct']]->id);

            case QuestionType::MultipleChoice:
                $options = $items((array) $spec['options']);
                $correct = array_values(array_map(fn (mixed $i): string => $options[(int) $i]->id, (array) $spec['correct']));

                return new MultipleChoiceDefinition($options, $correct, MultipleChoiceScoring::from((string) $spec['scoring']));

            case QuestionType::TrueFalse:
                return new TrueFalseDefinition((bool) $spec['correct']);

            case QuestionType::Text:
                return new TextDefinition((int) $spec['max_length'], isset($spec['reference']) ? (string) $spec['reference'] : null);

            case QuestionType::Numeric:
                return new NumericDefinition((string) $spec['correct'], (string) $spec['tolerance'], isset($spec['unit']) ? (string) $spec['unit'] : null);

            case QuestionType::Ordering:
                $ordered = $items((array) $spec['items']);

                // Stored in a shuffled order (CSPRNG); correct_order keeps the real sequence.
                return new OrderingDefinition(array_values(Arr::shuffle($ordered)), array_map(fn (Item $item): string => $item->id, $ordered));

            case QuestionType::Matching:
                /** @var list<array{string, string}> $pairs */
                $pairs = $spec['pairs'];
                $left = $items(array_column($pairs, 0));
                $right = $items(array_column($pairs, 1));
                $distractors = $items((array) ($spec['distractors'] ?? []));

                return new MatchingDefinition(
                    $left,
                    array_values(Arr::shuffle([...$right, ...$distractors])),
                    array_map(fn (Item $l, Item $r): array => ['left' => $l->id, 'right' => $r->id], $left, $right),
                );
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Category>  $categories
     * @param  array<string, list<Question>>  $questions
     * @return array<string, Test>
     */
    private function tests(array $users, array $categories, array $questions): array
    {
        $now = CarbonImmutable::now();

        $definitions = [
            'algebra' => ['teacher1', 'algebra', 'ალგებრის საფუძვლები', ['algebra'], [
                'status' => TestStatus::Published, 'opens_at' => $now->subWeeks(3), 'closes_at' => $now->addWeeks(2),
            ]],
            'geometry' => ['teacher1', 'geometry', 'Geometry quiz', ['geometry'], [
                'status' => TestStatus::Published, 'time_limit_minutes' => 20, 'max_attempts' => 2, 'shuffle_options' => true,
            ]],
            'georgian-history' => ['teacher1', 'georgian-history', 'საქართველოს ისტორია: შუა საუკუნეები', ['georgian-history'], [
                'status' => TestStatus::Published, 'opens_at' => $now->subWeeks(6), 'closes_at' => $now->subWeek(),
                'results_visibility' => ResultsVisibility::AfterClose, 'show_correct_answers' => true,
            ]],
            'world-history' => ['teacher1', 'world-history', 'World history: the modern era', ['world-history'], [
                'status' => TestStatus::Published, 'opens_at' => $now->addWeek(), 'closes_at' => $now->addWeeks(3),
            ]],
            'php' => ['teacher2', 'php', 'PHP fundamentals', ['php', 'programming'], [
                'status' => TestStatus::Published, 'time_limit_minutes' => 30, 'shuffle_questions' => true,
                'selection_mode' => 'random_pool', 'random_question_count' => 6,
            ]],
            'javascript' => ['teacher2', 'javascript', 'JavaScript basics', ['javascript'], [
                'status' => TestStatus::Draft,
            ]],
            'biology' => ['teacher1', 'biology', 'ბიოლოგია: უჯრედიდან ორგანიზმამდე', ['biology'], [
                'status' => TestStatus::Archived,
            ]],
            'english' => ['teacher2', 'english-grammar', 'English grammar B1', ['english-grammar'], [
                'status' => TestStatus::Published, 'opens_at' => $now->subWeeks(4), 'closes_at' => $now->addMonth(),
                'results_visibility' => ResultsVisibility::Manual, 'max_attempts' => 3,
            ]],
        ];

        $tests = [];

        foreach ($definitions as $key => [$teacher, $category, $title, $questionSources, $settings]) {
            $test = new Test([
                'category_id' => $categories[$category]->id,
                'title' => $title,
                'description' => "Demo test: {$title}.",
                'pass_percent' => 60,
                'selection_mode' => $settings['selection_mode'] ?? 'fixed',
                'random_question_count' => $settings['random_question_count'] ?? null,
                'time_limit_minutes' => $settings['time_limit_minutes'] ?? null,
                'max_attempts' => $settings['max_attempts'] ?? null,
                'shuffle_questions' => $settings['shuffle_questions'] ?? false,
                'shuffle_options' => $settings['shuffle_options'] ?? false,
                'opens_at' => $settings['opens_at'] ?? null,
                'closes_at' => $settings['closes_at'] ?? null,
                'results_visibility' => $settings['results_visibility'] ?? ResultsVisibility::Immediately,
                'show_correct_answers' => $settings['show_correct_answers'] ?? false,
            ]);
            $test->owner_id = $users[$teacher]->id;
            $test->status = $settings['status'];
            $test->published_at = $settings['status'] === TestStatus::Draft ? null : $now->subWeeks(5);
            $test->save();

            $position = 1;

            foreach ($questionSources as $source) {
                foreach ($questions[$source] ?? [] as $question) {
                    $test->questions()->attach($question->id, ['position' => $position++, 'points_override' => null]);
                }
            }

            $tests[$key] = $test;
        }

        return $tests;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Test>  $tests
     */
    private function groups(array $users, array $tests): void
    {
        $groups = [
            ['teacher1', '10ა კლასი', 'Mathematics and history, class 10A.', ['student1', 'student2', 'student3'], ['algebra', 'geometry', 'georgian-history', 'world-history']],
            ['teacher2', 'Programming club', 'After-school PHP and JavaScript practice.', ['student3', 'student4', 'student5'], ['php']],
            ['teacher2', 'English B1', 'Grammar practice for B1 learners.', ['student1', 'student2', 'student5'], ['english']],
        ];

        foreach ($groups as [$teacher, $name, $description, $members, $assigned]) {
            $group = new Group(['name' => $name, 'description' => $description]);
            $group->owner_id = $users[$teacher]->id;
            $group->invite_code = Group::generateInviteCode();
            $group->save();

            $group->members()->attach(
                array_map(fn (string $student): int => $users[$student]->id, $members),
                ['joined_at' => now()->subWeeks(5)],
            );
            $group->tests()->attach(
                array_map(fn (string $test): int => $tests[$test]->id, $assigned),
                ['assigned_at' => now()->subWeeks(4)],
            );
        }
    }

    /**
     * Graded attempts with varied scores on fixed-question tests (the random-pool
     * test gets no attempts here: selection logic is Stage 5).
     *
     * @param  array<string, User>  $users
     * @param  array<string, Test>  $tests
     */
    private function attempts(array $users, array $tests): void
    {
        // [student, test, attempt number, correct answers, days ago]
        $graded = [
            ['student1', 'algebra', 1, 8, 20], ['student2', 'algebra', 1, 5, 18], ['student3', 'algebra', 1, 3, 17],
            ['student1', 'geometry', 1, 2, 15], ['student1', 'geometry', 2, 5, 12], ['student2', 'geometry', 1, 4, 14],
            ['student3', 'geometry', 1, 5, 11],
            ['student1', 'georgian-history', 1, 5, 30], ['student2', 'georgian-history', 1, 6, 29], ['student3', 'georgian-history', 1, 2, 28],
            ['student1', 'english', 1, 7, 9], ['student2', 'english', 1, 4, 8], ['student5', 'english', 1, 9, 6], ['student5', 'english', 2, 9, 2],
        ];

        foreach ($graded as [$student, $test, $number, $correct, $daysAgo]) {
            $started = CarbonImmutable::now()->subDays($daysAgo)->setTime(10, 0)->addMinutes($number * 37);

            $attempt = Attempt::factory()
                ->for($tests[$test])
                ->for($users[$student])
                ->gradedWith($correct)
                ->create([
                    'attempt_number' => $number,
                    'started_at' => $started,
                    'submitted_at' => $started->addMinutes(14),
                    'graded_at' => $started->addMinutes(15),
                ]);

            if ($attempt->fresh()?->passed === true) {
                Certificate::factory()->create(['attempt_id' => $attempt->id, 'issued_at' => $started->addMinutes(16)]);
            }
        }

        // One attempt in progress and one finished by the server when time ran out.
        Attempt::factory()->for($tests['algebra'])->for($users['student3'])->inProgress()->create(['attempt_number' => 2]);
        Attempt::factory()->for($tests['geometry'])->for($users['student2'])->timedOut(20)->create(['attempt_number' => 2]);
    }
}
