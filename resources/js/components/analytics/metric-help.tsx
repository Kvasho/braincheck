import { CircleHelp } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/** A small "?" that explains a metric in plain words (focusable, so keyboard users get it too). */
export function MetricHelp({ label, text }: { label: string; text: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    className="inline-flex rounded text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <CircleHelp className="size-3.5" aria-hidden="true" />
                </button>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">{text}</TooltipContent>
        </Tooltip>
    );
}
