import { useState } from 'react';
import { plainToRichText, richTextToPlain } from '@/components/rich-text';
import { Textarea } from '@/components/ui/textarea';
import type { RichTextDoc } from '@/types';

/**
 * Editor for rich text fields. Its contract (value / onChange with a
 * RichTextDoc) is what the Tiptap editor will implement.
 *
 * TEMPORARY FALLBACK: the Tiptap packages could not be installed (npm registry
 * blocked by a TLS-intercepting proxy), so this edits plain paragraphs: blank
 * lines separate paragraphs, line breaks become hard breaks. It produces valid
 * documents, but editing a document that has formatting keeps only its text.
 */
export function RichTextEditor({
    id,
    value,
    onChange,
    maxLength = 10_000,
    rows = 5,
    invalid = false,
}: {
    id: string;
    value: RichTextDoc | null;
    onChange: (value: RichTextDoc) => void;
    maxLength?: number;
    rows?: number;
    invalid?: boolean;
}) {
    const [text, setText] = useState(() => richTextToPlain(value));

    return (
        <Textarea
            id={id}
            rows={rows}
            value={text}
            maxLength={maxLength}
            aria-invalid={invalid || undefined}
            onChange={(event) => {
                setText(event.target.value);
                onChange(plainToRichText(event.target.value));
            }}
        />
    );
}
