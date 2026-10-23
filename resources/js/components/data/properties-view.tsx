import { useTranslation } from '@/hooks/use-translation';

/**
 * Key–value view of arbitrary JSON (audit properties). Everything is rendered
 * as React text nodes, so values like "<script>" stay inert text; there is no
 * HTML rendering path here (and `dangerouslySetInnerHTML` is a lint error).
 */
export function PropertiesView({ value }: { value: unknown }) {
    const { t } = useTranslation();

    if (
        value === null ||
        (typeof value === 'object' && Object.keys(value).length === 0)
    ) {
        return <p className="text-sm text-muted-foreground">{t('None')}</p>;
    }

    return <Node value={value} />;
}

function Node({ value }: { value: unknown }) {
    if (Array.isArray(value)) {
        return (
            <ol className="list-decimal space-y-1 pl-5">
                {value.map((item, index) => (
                    <li key={index}>
                        <Node value={item} />
                    </li>
                ))}
            </ol>
        );
    }

    if (value !== null && typeof value === 'object') {
        return (
            <dl className="grid grid-cols-[minmax(6rem,auto)_1fr] gap-x-4 gap-y-2 text-sm">
                {Object.entries(value).map(([key, item]) => (
                    <div key={key} className="contents">
                        <dt className="font-mono break-all text-muted-foreground">
                            {key}
                        </dt>
                        <dd className="min-w-0 break-words">
                            <Node value={item} />
                        </dd>
                    </div>
                ))}
            </dl>
        );
    }

    return (
        <span className="font-mono text-sm whitespace-pre-wrap">
            {typeof value === 'string'
                ? value
                : (JSON.stringify(value) ?? String(value as undefined))}
        </span>
    );
}
