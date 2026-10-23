<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Question images on the private `local` disk (storage/app/private), under a
 * random name, always as re-encoded WebP. Never on a public disk: they are
 * served only by QuestionImageController after authorization.
 *
 * Files are never deleted when a question's image is replaced or removed:
 * attempt snapshots (Stage 7) keep referencing the old file, so history keeps
 * its image. (Clean-up of unreferenced files is a later maintenance task.)
 */
final readonly class QuestionImageStore
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'question-images';

    public function __construct(
        private ImageReencoder $reencoder,
        private Filesystems $filesystems,
    ) {}

    /**
     * Re-encode and store; returns the stored path ("question-images/<random>.webp").
     */
    public function store(UploadedFile $file): string
    {
        $path = self::DIRECTORY.'/'.Str::lower((string) Str::ulid()).'-'.Str::random(16).'.webp';

        $this->disk()->put($path, $this->reencoder->toWebp($file));

        return $path;
    }

    public function disk(): Filesystem
    {
        return $this->filesystems->disk(self::DISK);
    }

    /**
     * Only paths this store creates are ever read (defence in depth against a
     * tampered column value).
     */
    public static function isStoredPath(?string $path): bool
    {
        return $path !== null && preg_match('#^'.self::DIRECTORY.'/[a-z0-9]{26}-[A-Za-z0-9]{16}\.webp$#', $path) === 1;
    }
}
