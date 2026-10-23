<?php

declare(strict_types=1);

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Decodes an uploaded image and re-encodes it as WebP, at most MAX_WIDTH wide.
 *
 * Re-encoding is the security measure: only the decoded pixels survive, so
 * EXIF/GPS metadata, embedded payloads and polyglot files (e.g. PHP or HTML
 * hidden in a "JPEG") are destroyed. The client's file name and MIME type are
 * never trusted: the type is sniffed from the bytes and must decode as that type.
 *
 * Uses PHP's bundled GD (JPEG, PNG and WebP support required). The spec names
 * intervention/image; it can replace this class behind the same method.
 */
final class ImageReencoder
{
    public const int MAX_WIDTH = 1600;

    public const int MAX_SOURCE_DIMENSION = 4000;

    public const int QUALITY = 82;

    /** Sniffed MIME type => GD decoder. */
    private const array DECODERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];

    /**
     * @return string WebP bytes
     *
     * @throws InvalidArgumentException when the file is not a decodable JPEG/PNG/WebP within limits
     */
    public function toWebp(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new InvalidArgumentException('The upload could not be read.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $decoder = is_string($mime) ? (self::DECODERS[$mime] ?? null) : null;

        if ($decoder === null) {
            throw new InvalidArgumentException('Only JPEG, PNG and WebP images are allowed.');
        }

        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1
            || $size[0] > self::MAX_SOURCE_DIMENSION || $size[1] > self::MAX_SOURCE_DIMENSION) {
            throw new InvalidArgumentException('The image must be at most '.self::MAX_SOURCE_DIMENSION.'×'.self::MAX_SOURCE_DIMENSION.' pixels.');
        }

        $image = @$decoder($path);

        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('The image could not be decoded.');
        }

        $image = $this->resized($image);

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $written = imagewebp($image, null, self::QUALITY);
        $webp = (string) ob_get_clean();

        if (! $written || $webp === '') {
            throw new InvalidArgumentException('The image could not be encoded.');
        }

        return $webp;
    }

    private function resized(GdImage $image): GdImage
    {
        $width = imagesx($image);

        if ($width <= self::MAX_WIDTH) {
            return $image;
        }

        $resized = imagescale($image, self::MAX_WIDTH, -1, IMG_BICUBIC);

        return $resized instanceof GdImage ? $resized : $image;
    }
}
