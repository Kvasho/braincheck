<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Shared\LocalizedName;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = [
            ['მათემატიკა', 'Mathematics'],
            ['ისტორია', 'History'],
            ['პროგრამირება', 'Programming'],
            ['ბიოლოგია', 'Biology'],
            ['გეოგრაფია', 'Geography'],
            ['ფიზიკა', 'Physics'],
        ];

        [$ka, $en] = fake()->randomElement($subjects);

        return [
            'parent_id' => null,
            'name' => new LocalizedName($ka, $en),
            'slug' => Str::slug($en).'-'.Str::lower(Str::random(6)),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
