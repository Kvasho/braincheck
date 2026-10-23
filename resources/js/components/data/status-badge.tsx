import type { LucideIcon } from 'lucide-react';
import {
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleMinus,
    CircleX,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export type StatusTone = 'success' | 'warning' | 'danger' | 'neutral' | 'info';

const tones: Record<
    StatusTone,
    {
        variant:
            | 'success'
            | 'warning'
            | 'destructive'
            | 'secondary'
            | 'outline';
        icon: LucideIcon;
    }
> = {
    success: { variant: 'success', icon: CircleCheck },
    warning: { variant: 'warning', icon: CircleAlert },
    danger: { variant: 'destructive', icon: CircleX },
    neutral: { variant: 'secondary', icon: CircleMinus },
    info: { variant: 'outline', icon: CircleDashed },
};

/**
 * A status with colour *and* icon *and* text: never colour alone.
 */
export function StatusBadge({
    tone,
    label,
    icon,
}: {
    tone: StatusTone;
    /** Already translated. */
    label: string;
    icon?: LucideIcon;
}) {
    const { variant, icon: defaultIcon } = tones[tone];
    const Icon = icon ?? defaultIcon;

    return (
        <Badge variant={variant}>
            <Icon aria-hidden="true" />
            {label}
        </Badge>
    );
}
