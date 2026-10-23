<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\User;
use App\Support\QuestionImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(QuestionImageStore::DISK);
});

/**
 * A real JPEG (GD) with an EXIF APP1 segment that holds a GPS-like marker,
 * optionally followed by a script payload (a polyglot).
 */
function jpegWithExif(string $marker = 'GPS-SECRET-41.7151N-44.8271E', string $trailer = ''): UploadedFile
{
    $image = imagecreatetruecolor(320, 200);
    imagefilledrectangle($image, 0, 0, 319, 199, (int) imagecolorallocate($image, 30, 60, 90));
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // APP1 "Exif\0\0" + a minimal little-endian TIFF header + the marker bytes.
    $exif = "Exif\0\0"."II*\0\x08\0\0\0".$marker;
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $bytes = substr($jpeg, 0, 2).$app1.substr($jpeg, 2).$trailer;

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'holiday.jpg', 'image/jpeg', null, true);
}

/**
 * @return array<string, mixed>
 */
function imageQuestionPayload(?UploadedFile $image = null, array $overrides = []): array
{
    return [
        'type' => QuestionType::TrueFalse->value,
        'body' => json_encode(App\Domain\Shared\RichTextDocument::fromPlainText('Is this the Kura river?')->toArray()),
        'explanation' => 'null',
        'tags' => '[]',
        'difficulty' => 'easy',
        'points' => '1',
        'definition' => json_encode(['correct' => true]),
        'image' => $image,
        ...$overrides,
    ];
}

function imageTeacher(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

it('re-encodes uploads to WebP, strips EXIF and stores them privately under a random name', function (): void {
    imageTeacher();

    $this->post(route('teacher.questions.store'), imageQuestionPayload(jpegWithExif()))->assertSessionHasNoErrors();

    $path = Question::query()->sole()->image_path;
    $bytes = (string) Storage::disk(QuestionImageStore::DISK)->get((string) $path);

    expect(QuestionImageStore::isStoredPath($path))->toBeTrue()
        ->and($path)->not->toContain('holiday')
        ->and(substr($bytes, 0, 4))->toBe('RIFF')
        ->and(substr($bytes, 8, 4))->toBe('WEBP')
        ->and($bytes)->not->toContain('GPS-SECRET')
        ->and($bytes)->not->toContain('Exif');
});

it('destroys a payload hidden in a valid image (polyglot)', function (): void {
    imageTeacher();

    $this->post(route('teacher.questions.store'), imageQuestionPayload(jpegWithExif(trailer: '<?php system($_GET["c"]); ?><script>alert(1)</script>')))
        ->assertSessionHasNoErrors();

    $bytes = (string) Storage::disk(QuestionImageStore::DISK)->get((string) Question::query()->sole()->image_path);

    expect($bytes)->not->toContain('<?php')->not->toContain('<script>');
});

it('downscales wide images to at most 1600 pixels', function (): void {
    imageTeacher();

    $this->post(route('teacher.questions.store'), imageQuestionPayload(UploadedFile::fake()->image('wide.png', 3000, 1000)))
        ->assertSessionHasNoErrors();

    $bytes = (string) Storage::disk(QuestionImageStore::DISK)->get((string) Question::query()->sole()->image_path);
    $size = getimagesizefromstring($bytes);

    expect($size[0] ?? null)->toBe(1600)->and($size[1] ?? null)->toBe(533);
});

it('rejects files that are not JPEG, PNG or WebP images', function (UploadedFile $file): void {
    imageTeacher();

    $this->post(route('teacher.questions.store'), imageQuestionPayload($file))->assertSessionHasErrors('image');

    expect(Question::query()->count())->toBe(0)
        ->and(Storage::disk(QuestionImageStore::DISK)->allFiles())->toBe([]);
})->with([
    'PHP renamed to .jpg' => fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo shell_exec($_GET["c"]); ?>'),
    'SVG' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>'),
    'GIF' => fn () => UploadedFile::fake()->image('animated.gif', 10, 10),
    'oversize (5 MB)' => fn () => UploadedFile::fake()->image('big.jpg', 100, 100)->size(5 * 1024),
    'too many pixels' => fn () => UploadedFile::fake()->image('huge.png', 4001, 10),
]);

it('replaces and removes an image without deleting the old file (snapshots may use it)', function (): void {
    imageTeacher();
    $this->post(route('teacher.questions.store'), imageQuestionPayload(jpegWithExif()));
    $question = Question::query()->sole();
    $first = $question->image_path;

    $this->put(route('teacher.questions.update', $question), imageQuestionPayload(UploadedFile::fake()->image('new.png', 50, 50)))
        ->assertSessionHasNoErrors();
    $second = $question->fresh()?->image_path;

    $this->put(route('teacher.questions.update', $question), imageQuestionPayload(null, ['remove_image' => '1']))
        ->assertSessionHasNoErrors();

    expect($second)->not->toBe($first)
        ->and($question->fresh()?->image_path)->toBeNull()
        ->and(Storage::disk(QuestionImageStore::DISK)->exists((string) $first))->toBeTrue()
        ->and(Storage::disk(QuestionImageStore::DISK)->exists((string) $second))->toBeTrue();
});

describe('serving', function (): void {
    it('serves the image to its owner with safe headers', function (): void {
        imageTeacher();
        $this->post(route('teacher.questions.store'), imageQuestionPayload(jpegWithExif()));
        $question = Question::query()->sole();

        $response = $this->get(route('question-images.show', $question))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('image/webp')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Cache-Control'))->toContain('private');
    });

    it('serves it to admins but not to other teachers or students', function (): void {
        imageTeacher();
        $this->post(route('teacher.questions.store'), imageQuestionPayload(jpegWithExif()));
        $question = Question::query()->sole();

        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())->get(route('question-images.show', $question))->assertForbidden();
        $this->actingAs(User::factory()->student()->create())->get(route('question-images.show', $question))->assertForbidden();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())->get(route('question-images.show', $question))->assertOk();
    });

    it('looks images up by question ULID only, so path traversal is impossible', function (string $parameter): void {
        imageTeacher();

        $this->get('/question-images/'.$parameter)->assertNotFound();
    })->with(['..%2F..%2F.env', '../../.env', '1', 'question-images%2Fx.webp']);

    it('refuses a tampered image path', function (): void {
        $teacher = imageTeacher();
        $question = Question::factory()->for($teacher, 'owner')->create();
        $question->forceFill(['image_path' => '../.env'])->save();

        $this->get(route('question-images.show', $question))->assertNotFound();
    });
});
