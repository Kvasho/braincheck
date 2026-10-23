<?php

declare(strict_types=1);

use App\Domain\Shared\RichTextDocument;
use App\Exceptions\InvalidRichText;
use App\Rules\RichText;
use Illuminate\Support\Facades\Validator;

/**
 * @param  list<array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function doc(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

/**
 * @param  list<array<string, mixed>>  $marks
 * @return array<string, mixed>
 */
function para(string $text, array $marks = []): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, ...($marks === [] ? [] : ['marks' => $marks])]]];
}

it('accepts every allowed node and mark', function (): void {
    $document = RichTextDocument::fromArray(doc([
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Title']]],
        para('Water is H', [['type' => 'bold']]),
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'italic'], ['type' => 'underline']]],
            ['type' => 'text', 'text' => '2', 'marks' => [['type' => 'superscript']]],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'O', 'marks' => [['type' => 'subscript']]],
            ['type' => 'text', 'text' => 'echo', 'marks' => [['type' => 'code']]],
        ]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [para('one')]]]],
        ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [['type' => 'listItem', 'content' => [para('three')]]]],
        ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => '<?php echo 1;']]],
        ['type' => 'blockquote', 'content' => [para('quoted')]],
    ]));

    expect($document->toArray()['content'])->toHaveCount(7)
        ->and($document->isEmpty())->toBeFalse();
});

it('keeps script-like content as inert text', function (): void {
    $document = RichTextDocument::fromArray(doc([para('<script>alert(1)</script><img src=x onerror=alert(1)>')]));

    expect($document->plainText)->toBe('<script>alert(1)</script><img src=x onerror=alert(1)>')
        ->and($document->toArray()['content'][0]['content'][0])->toBe(['type' => 'text', 'text' => '<script>alert(1)</script><img src=x onerror=alert(1)>']);
});

it('rejects anything outside the allowlist', function (array $document): void {
    expect(fn () => RichTextDocument::fromArray($document))->toThrow(InvalidRichText::class);
})->with([
    'not a doc' => [['type' => 'paragraph']],
    'unknown node' => [doc([['type' => 'image', 'attrs' => ['src' => 'x']]])],
    'raw html node' => [doc([['type' => 'html', 'content' => [['type' => 'text', 'text' => '<b>x</b>']]]])],
    'link mark' => [doc([para('click', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]])])],
    'unknown mark' => [doc([para('x', [['type' => 'highlight']])])],
    'mark with attrs' => [doc([para('x', [['type' => 'bold', 'attrs' => ['style' => 'color:red']]])])],
    'unknown node key' => [doc([['type' => 'paragraph', 'onclick' => 'x']])],
    'unknown attribute' => [doc([['type' => 'paragraph', 'attrs' => ['style' => 'x']]])],
    'heading level 1' => [doc([['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'x']]]])],
    'heading without level' => [doc([['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'x']]]])],
    'code language injection' => [doc([['type' => 'codeBlock', 'attrs' => ['language' => '"><script>'], 'content' => [['type' => 'text', 'text' => 'x']]]])],
    'text directly in doc' => [doc([['type' => 'text', 'text' => 'x']])],
    'paragraph inside paragraph' => [doc([['type' => 'paragraph', 'content' => [para('x')]]])],
    'list item outside a list' => [doc([['type' => 'listItem', 'content' => [para('x')]]])],
    'empty text node' => [doc([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '']]]])],
    'marks in a code block' => [doc([['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'bold']]]]]])],
    'content not a list' => [doc([['type' => 'paragraph', 'content' => ['a' => ['type' => 'text', 'text' => 'x']]]])],
    'empty list' => [doc([['type' => 'bulletList', 'content' => []]])],
]);

it('limits nesting depth', function (): void {
    $node = para('deep');

    foreach (range(1, 4) as $level) {
        $node = ['type' => 'blockquote', 'content' => [$node]];
    }

    expect(RichTextDocument::fromArray(doc([$node]))->plainText)->toBe('deep');

    foreach (range(1, 5) as $level) {
        $node = ['type' => 'blockquote', 'content' => [$node]];
    }

    expect(fn () => RichTextDocument::fromArray(doc([$node])))->toThrow(InvalidRichText::class, 'nested');
});

it('limits the total text length and the JSON size', function (): void {
    expect(RichTextDocument::fromArray(doc([para(str_repeat('a', 10_000))]))->plainText)->toHaveLength(10_000)
        ->and(fn () => RichTextDocument::fromArray(doc([para(str_repeat('a', 6_000)), para(str_repeat('b', 4_001))])))->toThrow(InvalidRichText::class, 'longer')
        ->and(fn () => RichTextDocument::fromArray(doc(array_fill(0, 3_000, ['type' => 'paragraph', 'content' => [['type' => 'hardBreak']]]))))->toThrow(InvalidRichText::class, 'KB');
});

it('extracts plain text', function (): void {
    $document = RichTextDocument::fromArray(doc([
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Title']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'H'], ['type' => 'text', 'text' => '2', 'marks' => [['type' => 'subscript']]], ['type' => 'text', 'text' => 'O'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'line two']]],
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [para('first')]],
            ['type' => 'listItem', 'content' => [para('second')]],
        ]],
    ]));

    expect($document->plainText)->toBe("Title\nH2O\nline two\nfirst\nsecond");
});

it('builds documents from plain text', function (): void {
    $document = RichTextDocument::fromPlainText("First line\nsecond line\n\nNew paragraph");

    expect($document->toArray())->toBe(doc([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First line'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'second line']]],
        para('New paragraph'),
    ]))->and(RichTextDocument::fromPlainText('   ')->isEmpty())->toBeTrue();
});

it('validates through the rule, requiring text when asked', function (): void {
    $passes = fn (mixed $value, bool $required = true): bool => Validator::make(['body' => $value], ['body' => [new RichText($required)]])->passes();

    expect($passes(doc([para('ok')])))->toBeTrue()
        ->and($passes(doc([])))->toBeFalse()
        ->and($passes(doc([]), false))->toBeTrue()
        ->and($passes('<p>html</p>'))->toBeFalse()
        ->and($passes(doc([['type' => 'image']])))->toBeFalse();
});
