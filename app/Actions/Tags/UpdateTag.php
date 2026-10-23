<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Tag;
use App\Models\User;
use App\Support\TagSlug;
use Illuminate\Support\Facades\DB;

final class UpdateTag
{
    public function __construct(
        private readonly TagSlug $slug,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Tag $tag, string $name, ?string $slug, User $actor): Tag
    {
        return DB::transaction(function () use ($tag, $name, $slug, $actor): Tag {
            $before = ['name' => $tag->name, 'slug' => $tag->slug];

            $tag->name = $name;
            $tag->slug = $slug ?? $this->slug->generate($name, $tag);
            $tag->save();

            $this->recordAuditEvent->handle(AuditAction::TagUpdated, $actor, $tag, [
                'from' => $before,
                'to' => ['name' => $tag->name, 'slug' => $tag->slug],
            ]);

            return $tag;
        });
    }
}
