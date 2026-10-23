/**
 * Rich text = a ProseMirror / Tiptap JSON document, mirroring the allowlist of
 * App\Domain\Shared\RichTextDocument (tests/Arch/EnumParityTest.php checks the
 * two lists stay in sync). Never HTML.
 */
export const RICH_TEXT_NODES = [
    'doc',
    'paragraph',
    'text',
    'heading',
    'bulletList',
    'orderedList',
    'listItem',
    'codeBlock',
    'hardBreak',
    'blockquote',
] as const;
export type RichTextNodeType = (typeof RICH_TEXT_NODES)[number];

export const RICH_TEXT_MARKS = [
    'bold',
    'italic',
    'underline',
    'code',
    'subscript',
    'superscript',
] as const;
export type RichTextMarkType = (typeof RICH_TEXT_MARKS)[number];

export type RichTextMark = { type: RichTextMarkType };

export type RichTextNode =
    | { type: 'text'; text: string; marks?: RichTextMark[] }
    | { type: 'hardBreak' }
    | { type: 'paragraph'; content?: RichTextNode[] }
    | { type: 'heading'; attrs: { level: 3 | 4 }; content?: RichTextNode[] }
    | { type: 'bulletList'; content: RichTextNode[] }
    | {
          type: 'orderedList';
          attrs?: { start?: number };
          content: RichTextNode[];
      }
    | { type: 'listItem'; content: RichTextNode[] }
    | {
          type: 'codeBlock';
          attrs?: { language?: string | null };
          content?: RichTextNode[];
      }
    | { type: 'blockquote'; content: RichTextNode[] };

export type RichTextDoc = { type: 'doc'; content: RichTextNode[] };
