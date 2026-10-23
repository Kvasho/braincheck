<?php

declare(strict_types=1);

namespace App\Actions\Localization;

use App\Enums\Locale;
use Illuminate\Support\Facades\Cache;
use JsonException;
use RuntimeException;

final class LoadTranslations
{
    /**
     * UI translations (lang/{locale}.json) for the frontend, cached server-side.
     *
     * The path is built from the Locale enum only. The cache key includes the
     * file's modification time, so editing a translation file invalidates it.
     *
     * @return array<string, string>
     */
    public function handle(Locale $locale): array
    {
        $path = lang_path($locale->value.'.json');
        $version = is_file($path) ? (string) filemtime($path) : 'missing';

        return Cache::rememberForever(
            "ui-translations:{$locale->value}:{$version}",
            fn (): array => $this->read($path),
        );
    }

    /**
     * @return array<string, string>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid translation file [{$path}].", previous: $exception);
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter(
            $decoded,
            fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
