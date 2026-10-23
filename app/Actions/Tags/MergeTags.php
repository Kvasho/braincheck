<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merges $source into $target in one transaction: every question tagged with
 * the source becomes tagged with the target (no duplicate pivot rows; archived
 * questions included, so nothing is silently lost), then the source is deleted.
 */
final class MergeTags
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @throws ValidationException
     */
    public function handle(Tag $source, Tag $target, User $actor): void
    {
        if ($source->is($target)) {
            throw ValidationException::withMessages(['target' => __('A tag cannot be merged into itself.')]);
        }

        DB::transaction(function () use ($source, $target, $actor): void {
            // Lock both tags in id order (no deadlock between opposite merges).
            Tag::query()->whereKey([$source->id, $target->id])->orderBy('id')->lockForUpdate()->get();

            $questionIds = DB::table('question_tag')->where('tag_id', $source->id)->pluck('question_id');
            $alreadyOnTarget = DB::table('question_tag')->where('tag_id', $target->id)->whereIn('question_id', $questionIds)->count();

            DB::table('question_tag')->insertOrIgnore(
                $questionIds->map(fn (mixed $questionId): array => ['question_id' => $questionId, 'tag_id' => $target->id])->all(),
            );

            $this->recordAuditEvent->handle(AuditAction::TagMerged, $actor, $target, [
                'source' => ['ulid' => $source->ulid, 'name' => $source->name, 'slug' => $source->slug],
                'target' => ['ulid' => $target->ulid, 'name' => $target->name, 'slug' => $target->slug],
                'questions_moved' => $questionIds->count() - $alreadyOnTarget,
                'questions_already_tagged' => $alreadyOnTarget,
            ]);

            $source->delete();
        });
    }
}
