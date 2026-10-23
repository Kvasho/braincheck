<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a tag; its question links go with it (pivot `ON DELETE CASCADE`),
 * archived questions included. The number of links is recorded.
 */
final class DeleteTag
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(Tag $tag, User $actor): void
    {
        DB::transaction(function () use ($tag, $actor): void {
            $this->recordAuditEvent->handle(AuditAction::TagDeleted, $actor, $tag, [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'questions_untagged' => DB::table('question_tag')->where('tag_id', $tag->id)->count(),
            ]);

            $tag->delete();
        });
    }
}
