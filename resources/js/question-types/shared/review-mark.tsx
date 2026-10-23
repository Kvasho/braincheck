import { Check, X } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * A reviewed entry: correct (success + check icon + "neuron pulse"), wrong
 * (destructive + cross icon) or neutral (correctness not revealed). Colour is
 * never the only signal: icon and screen-reader text carry it too.
 */
export function ReviewMark({
    correct,
    selected = false,
    children,
}: {
    correct: boolean | undefined;
    selected?: boolean;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            initial={false}
            animate={
                correct && !reduceMotion
                    ? { scale: [1, 1.02, 1], opacity: [0.85, 1, 1] }
                    : undefined
            }
            transition={{ duration: 0.6, ease: 'easeOut' }}
            className={cn(
                'flex items-start gap-2 rounded-lg border p-3',
                correct === true && 'border-success bg-success/10',
                correct === false &&
                    selected &&
                    'border-destructive bg-destructive/10',
                selected && correct === undefined && 'border-primary',
            )}
        >
            <span className="mt-0.5 shrink-0" aria-hidden="true">
                {correct === true && <Check className="size-4 text-success" />}
                {correct === false && selected && (
                    <X className="size-4 text-destructive" />
                )}
            </span>
            <div className="min-w-0 flex-1 break-words">{children}</div>
            <span className="sr-only">
                {correct === true
                    ? t('Correct')
                    : correct === false && selected
                      ? t('Incorrect')
                      : ''}
                {selected ? ` (${t('your answer')})` : ''}
            </span>
        </motion.div>
    );
}
