import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { RichTextDoc, RichTextMark, RichTextNode } from '@/types';

/**
 * Renders a rich text document by mapping each allowlisted node to a React
 * element. Text is always a React text node (escaped); there is no HTML path.
 * Unknown nodes or marks render nothing (and warn in development).
 */
export function RichText({
    doc,
    className,
}: {
    doc: RichTextDoc | null | undefined;
    className?: string;
}) {
    if (!doc || doc.type !== 'doc') {
        return null;
    }

    return (
        <div className={cn('rich-text space-y-3 leading-relaxed', className)}>
            {renderChildren(doc.content)}
        </div>
    );
}

function renderChildren(nodes: RichTextNode[] | undefined): ReactNode[] {
    return (nodes ?? []).map((node, index) => renderNode(node, index));
}

function warnUnknown(kind: string, value: unknown): null {
    if (import.meta.env.DEV) {
        console.warn(`RichText: ignored unknown ${kind}`, value);
    }

    return null;
}

function renderNode(node: RichTextNode, key: number): ReactNode {
    switch (node.type) {
        case 'text':
            return renderMarks(node.text, node.marks ?? [], key);
        case 'hardBreak':
            return <br key={key} />;
        case 'paragraph':
            return <p key={key}>{renderChildren(node.content)}</p>;
        case 'heading':
            return node.attrs.level === 3 ? (
                <h3 key={key} className="text-lg font-semibold">
                    {renderChildren(node.content)}
                </h3>
            ) : (
                <h4 key={key} className="font-semibold">
                    {renderChildren(node.content)}
                </h4>
            );
        case 'bulletList':
            return (
                <ul key={key} className="list-disc space-y-1 pl-6">
                    {renderChildren(node.content)}
                </ul>
            );
        case 'orderedList':
            return (
                <ol
                    key={key}
                    start={node.attrs?.start}
                    className="list-decimal space-y-1 pl-6"
                >
                    {renderChildren(node.content)}
                </ol>
            );
        case 'listItem':
            return <li key={key}>{renderChildren(node.content)}</li>;
        case 'codeBlock':
            return (
                <pre
                    key={key}
                    className="overflow-x-auto rounded-md bg-muted p-3 font-mono text-sm"
                >
                    <code>{renderChildren(node.content)}</code>
                </pre>
            );
        case 'blockquote':
            return (
                <blockquote
                    key={key}
                    className="border-l-2 border-primary pl-4 text-muted-foreground"
                >
                    {renderChildren(node.content)}
                </blockquote>
            );
        default: {
            const unknown: never = node;

            return warnUnknown('node', unknown);
        }
    }
}

function renderMarks(
    text: string,
    marks: RichTextMark[],
    key: number,
): ReactNode {
    return marks.reduceRight<ReactNode>(
        (content, mark) => {
            switch (mark.type) {
                case 'bold':
                    return <strong>{content}</strong>;
                case 'italic':
                    return <em>{content}</em>;
                case 'underline':
                    return <u>{content}</u>;
                case 'code':
                    return (
                        <code className="rounded bg-muted px-1 font-mono text-[0.9em]">
                            {content}
                        </code>
                    );
                case 'subscript':
                    return <sub>{content}</sub>;
                case 'superscript':
                    return <sup>{content}</sup>;
                default: {
                    const unknown: never = mark.type;
                    warnUnknown('mark', unknown);

                    return content;
                }
            }
        },
        <span key={key}>{text}</span>,
    );
}

/**
 * Plain text of a document (blocks and hard breaks become newlines).
 */
export function richTextToPlain(doc: RichTextDoc | null | undefined): string {
    const walk = (
        nodes: RichTextNode[] | undefined,
        separator: string,
    ): string =>
        (nodes ?? [])
            .map((node) => {
                switch (node.type) {
                    case 'text':
                        return node.text;
                    case 'hardBreak':
                        return '\n';
                    case 'paragraph':
                    case 'heading':
                    case 'codeBlock':
                        return walk(node.content, '');
                    default:
                        return walk(node.content, '\n\n');
                }
            })
            .join(separator);

    return doc ? walk(doc.content, '\n\n') : '';
}

/**
 * Document from plain text: blank lines separate paragraphs, single line
 * breaks become hard breaks (mirrors RichTextDocument::fromPlainText()).
 */
export function plainToRichText(text: string): RichTextDoc {
    const paragraphs = text
        .replace(/\r\n/g, '\n')
        .trim()
        .split(/\n{2,}/)
        .filter((paragraph) => paragraph.trim() !== '');

    return {
        type: 'doc',
        content: paragraphs.map((paragraph) => ({
            type: 'paragraph',
            content: paragraph.split('\n').flatMap((line, index) => {
                const nodes: RichTextNode[] =
                    index > 0 ? [{ type: 'hardBreak' }] : [];

                return line === ''
                    ? nodes
                    : [...nodes, { type: 'text' as const, text: line }];
            }),
        })),
    };
}
