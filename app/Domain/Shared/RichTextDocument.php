<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Exceptions\InvalidRichText;
use JsonSerializable;

/**
 * Rich text (question body, explanation) as a ProseMirror / Tiptap JSON
 * document, validated against a strict allowlist. Never HTML: the frontend
 * renders it node by node with the same allowlist (components/rich-text.tsx),
 * so there is no string that could be injected as markup.
 *
 * Allowed nodes: doc, paragraph, text, heading (levels 3–4), bulletList,
 * orderedList, listItem, codeBlock, hardBreak, blockquote.
 * Allowed marks: bold, italic, underline, code, subscript, superscript.
 * No links, no images, no raw HTML. Unknown nodes, marks, attributes or keys
 * are rejected (not silently dropped).
 *
 * Limits: nesting depth ≤ MAX_DEPTH, total text ≤ MAX_TEXT_LENGTH characters,
 * encoded JSON ≤ MAX_BYTES.
 */
final readonly class RichTextDocument implements JsonSerializable
{
    public const int MAX_DEPTH = 8;

    public const int MAX_TEXT_LENGTH = 10_000;

    public const int MAX_BYTES = 65_536;

    /** Block nodes allowed inside doc, listItem and blockquote. */
    private const array BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'codeBlock', 'blockquote'];

    /** Inline nodes allowed inside paragraph and heading. */
    private const array INLINE = ['text', 'hardBreak'];

    /** @var array<string, list<string>> node type => allowed child types */
    private const array CHILDREN = [
        'doc' => self::BLOCKS,
        'paragraph' => self::INLINE,
        'heading' => self::INLINE,
        'bulletList' => ['listItem'],
        'orderedList' => ['listItem'],
        'listItem' => self::BLOCKS,
        'blockquote' => self::BLOCKS,
        'codeBlock' => ['text'],
        'text' => [],
        'hardBreak' => [],
    ];

    /** Every allowed node type (mirrored in resources/js/types/rich-text.ts). */
    public const array NODES = ['doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem', 'codeBlock', 'hardBreak', 'blockquote'];

    /** Every allowed mark (mirrored in resources/js/types/rich-text.ts). */
    public const array MARKS = ['bold', 'italic', 'underline', 'code', 'subscript', 'superscript'];

    /**
     * @param  array<string, mixed>  $document
     */
    private function __construct(
        private array $document,
        public string $plainText,
    ) {}

    /**
     * @param  array<array-key, mixed>  $document
     *
     * @throws InvalidRichText
     */
    public static function fromArray(array $document): self
    {
        $encoded = json_encode($document);

        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            throw InvalidRichText::because('the document is larger than '.(self::MAX_BYTES / 1024).' KB.');
        }

        if (($document['type'] ?? null) !== 'doc') {
            throw InvalidRichText::because('the root node must be a doc.');
        }

        $textLength = 0;
        $clean = self::node($document, 'doc', 1, $textLength);
        $clean['content'] ??= [];

        /** @var array<string, mixed> $clean */
        return new self($clean, self::plain($clean));
    }

    /**
     * Paragraphs from plain text: blank lines separate paragraphs, single line
     * breaks become hard breaks. Used for legacy text and the plain editor.
     *
     * @throws InvalidRichText
     */
    public static function fromPlainText(string $text): self
    {
        $paragraphs = preg_split('/\R{2,}/u', trim(str_replace("\r\n", "\n", $text))) ?: [];
        $content = [];

        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }

            $inline = [];

            foreach (preg_split('/\R/u', $paragraph) ?: [] as $index => $line) {
                if ($index > 0) {
                    $inline[] = ['type' => 'hardBreak'];
                }

                if ($line !== '') {
                    $inline[] = ['type' => 'text', 'text' => $line];
                }
            }

            $content[] = ['type' => 'paragraph', 'content' => $inline];
        }

        return self::fromArray(['type' => 'doc', 'content' => $content]);
    }

    public function isEmpty(): bool
    {
        return trim($this->plainText) === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->document;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->document;
    }

    /**
     * Validate one node and return its normalized form.
     *
     * @return array<string, mixed>
     */
    private static function node(mixed $node, string $expectedIn, int $depth, int &$textLength): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidRichText::because('the document is nested more than '.self::MAX_DEPTH.' levels deep.');
        }

        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            throw InvalidRichText::because('every node needs a type.');
        }

        $type = $node['type'];

        if (! array_key_exists($type, self::CHILDREN)) {
            throw InvalidRichText::because("the node type \"{$type}\" is not allowed.");
        }

        $unknown = array_diff(array_keys($node), ['type', 'content', 'text', 'marks', 'attrs']);

        if ($unknown !== []) {
            throw InvalidRichText::because('unknown node keys: '.implode(', ', $unknown).'.');
        }

        $clean = ['type' => $type];

        $attrs = self::attrs($type, $node['attrs'] ?? null);

        if ($attrs !== []) {
            $clean['attrs'] = $attrs;
        }

        if ($type === 'text') {
            if (! is_string($node['text'] ?? null) || $node['text'] === '' || isset($node['content'])) {
                throw InvalidRichText::because('a text node needs non-empty text and no content.');
            }

            $textLength += mb_strlen($node['text']);

            if ($textLength > self::MAX_TEXT_LENGTH) {
                throw InvalidRichText::because('the text is longer than '.self::MAX_TEXT_LENGTH.' characters.');
            }

            $clean['text'] = $node['text'];
            $marks = self::marks($node['marks'] ?? null, $expectedIn === 'codeBlock');

            if ($marks !== []) {
                $clean['marks'] = $marks;
            }

            return $clean;
        }

        if (isset($node['text']) || isset($node['marks'])) {
            throw InvalidRichText::because("a {$type} node can't have text or marks.");
        }

        if (array_key_exists('content', $node)) {
            if (! is_array($node['content']) || ! array_is_list($node['content'])) {
                throw InvalidRichText::because('content must be a list of nodes.');
            }

            $children = [];

            foreach ($node['content'] as $child) {
                $childType = is_array($child) && is_string($child['type'] ?? null) ? $child['type'] : '';

                if (! in_array($childType, self::CHILDREN[$type], true)) {
                    throw InvalidRichText::because("a \"{$childType}\" node is not allowed inside {$type}.");
                }

                $children[] = self::node($child, $type, $depth + 1, $textLength);
            }

            if ($children !== []) {
                $clean['content'] = $children;
            }
        }

        if (in_array($type, ['bulletList', 'orderedList', 'listItem', 'blockquote'], true) && ($clean['content'] ?? []) === []) {
            throw InvalidRichText::because("a {$type} node can't be empty.");
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private static function attrs(string $type, mixed $attrs): array
    {
        if ($attrs === null || $attrs === []) {
            return $type === 'heading'
                ? throw InvalidRichText::because('a heading needs a level.')
                : [];
        }

        if (! is_array($attrs)) {
            throw InvalidRichText::because('attrs must be an object.');
        }

        $allowed = match ($type) {
            'heading' => ['level'],
            'orderedList' => ['start', 'type'],
            'codeBlock' => ['language'],
            default => [],
        };

        $unknown = array_diff(array_keys($attrs), $allowed);

        if ($unknown !== []) {
            throw InvalidRichText::because("unknown {$type} attributes: ".implode(', ', $unknown).'.');
        }

        return match ($type) {
            'heading' => in_array($attrs['level'] ?? null, [3, 4], true)
                ? ['level' => $attrs['level']]
                : throw InvalidRichText::because('heading level must be 3 or 4.'),
            'orderedList' => self::orderedListAttrs($attrs),
            'codeBlock' => self::codeBlockAttrs($attrs),
            default => [],
        };
    }

    /**
     * @param  array<array-key, mixed>  $attrs
     * @return array<string, mixed>
     */
    private static function orderedListAttrs(array $attrs): array
    {
        $start = $attrs['start'] ?? 1;

        if (! is_int($start) || $start < 1 || $start > 10_000) {
            throw InvalidRichText::because('an ordered list must start at a number from 1 to 10000.');
        }

        if (($attrs['type'] ?? null) !== null) {
            throw InvalidRichText::because('ordered list types are not supported.');
        }

        return $start === 1 ? [] : ['start' => $start];
    }

    /**
     * @param  array<array-key, mixed>  $attrs
     * @return array<string, mixed>
     */
    private static function codeBlockAttrs(array $attrs): array
    {
        $language = $attrs['language'] ?? null;

        if ($language === null) {
            return [];
        }

        if (! is_string($language) || preg_match('/^[a-z0-9+#-]{1,20}$/', $language) !== 1) {
            throw InvalidRichText::because('the code language must be a short identifier such as "php".');
        }

        return ['language' => $language];
    }

    /**
     * @return list<array{type: string}>
     */
    private static function marks(mixed $marks, bool $inCodeBlock): array
    {
        if ($marks === null || $marks === []) {
            return [];
        }

        if ($inCodeBlock) {
            throw InvalidRichText::because('text in a code block can\'t have marks.');
        }

        if (! is_array($marks) || ! array_is_list($marks)) {
            throw InvalidRichText::because('marks must be a list.');
        }

        $clean = [];

        foreach ($marks as $mark) {
            if (! is_array($mark) || ! in_array($mark['type'] ?? null, self::MARKS, true)) {
                throw InvalidRichText::because('only bold, italic, underline, code, subscript and superscript are allowed.');
            }

            if (array_diff(array_keys($mark), ['type', 'attrs']) !== [] || ($mark['attrs'] ?? []) !== []) {
                throw InvalidRichText::because('marks can\'t have attributes.');
            }

            $clean[$mark['type']] = ['type' => $mark['type']];
        }

        return array_values($clean);
    }

    /**
     * Plain text: blocks separated by a newline, hard breaks as newlines.
     *
     * @param  array<string, mixed>  $node
     */
    private static function plain(array $node): string
    {
        if ($node['type'] === 'text') {
            return (string) $node['text'];
        }

        if ($node['type'] === 'hardBreak') {
            return "\n";
        }

        /** @var list<array<string, mixed>> $children */
        $children = $node['content'] ?? [];
        $parts = array_map(fn (array $child): string => self::plain($child), $children);

        $isInline = in_array($node['type'], ['paragraph', 'heading', 'codeBlock'], true);

        return trim(implode($isInline ? '' : "\n", $parts), "\n");
    }
}
