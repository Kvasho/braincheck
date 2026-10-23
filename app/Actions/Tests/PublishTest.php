<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Domain\Tests\PublishChecklist;
use App\Enums\TestStatus;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a draft once every PublishChecklist error passes (warnings don't
 * block). Only drafts can be published.
 */
final class PublishTest
{
    public function __construct(private readonly PublishChecklist $checklist) {}

    /**
     * @throws ValidationException
     */
    public function handle(Test $test): void
    {
        DB::transaction(function () use ($test): void {
            $locked = Test::query()->whereKey($test->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== TestStatus::Draft) {
                throw ValidationException::withMessages(['test' => __('Only draft tests can be published.')]);
            }

            $errors = $this->checklist->errors($locked);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $locked->status = TestStatus::Published;
            $locked->published_at = now()->toImmutable();
            $locked->save();
        });

        $test->refresh();
    }
}
