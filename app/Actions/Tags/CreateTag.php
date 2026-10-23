<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Tag;
use App\Models\User;
use App\Support\TagSlug;
use Illuminate\Support\Facades\DB;

final class CreateTag
{
    public function __construct(
        private readonly TagSlug $slug,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(string $name, ?string $slug, User $actor): Tag
    {
        return DB::transaction(function () use ($name, $slug, $actor): Tag {
            $tag = Tag::query()->create([
                'name' => $name,
                'slug' => $slug ?? $this->slug->generate($name),
            ]);

            $this->recordAuditEvent->handle(AuditAction::TagCreated, $actor, $tag, [
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]);

            return $tag;
        });
    }
}
