import { motion, useReducedMotion } from 'motion/react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

const RADIUS = 52;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * The score as a ring: the arc fills to the percent (animated once, not
 * under reduced motion), the number in the middle in tabular figures.
 * Passed rings use the success token, others the primary token.
 */
export function ScoreRing({
    percent,
    passed,
}: {
    percent: number;
    passed: boolean | null;
}) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion();
    const clamped = Math.max(0, Math.min(100, percent));
    const offset = CIRCUMFERENCE * (1 - clamped / 100);

    return (
        <div
            className="relative size-36 shrink-0"
            role="img"
            aria-label={t(':percent%', { percent: clamped.toFixed(2) })}
        >
            <svg viewBox="0 0 120 120" className="size-full -rotate-90">
                <circle
                    cx="60"
                    cy="60"
                    r={RADIUS}
                    fill="none"
                    strokeWidth="10"
                    className="stroke-muted"
                />
                <motion.circle
                    cx="60"
                    cy="60"
                    r={RADIUS}
                    fill="none"
                    strokeWidth="10"
                    strokeLinecap="round"
                    strokeDasharray={CIRCUMFERENCE}
                    className={cn(passed ? 'stroke-success' : 'stroke-primary')}
                    initial={
                        reduceMotion
                            ? false
                            : { strokeDashoffset: CIRCUMFERENCE }
                    }
                    animate={{ strokeDashoffset: offset }}
                    transition={
                        reduceMotion
                            ? { duration: 0 }
                            : { duration: 1.2, ease: 'easeOut' }
                    }
                />
            </svg>
            <span
                className="tabular absolute inset-0 flex items-center justify-center text-2xl font-semibold"
                aria-hidden="true"
            >
                {clamped.toFixed(0)}%
            </span>
        </div>
    );
}
