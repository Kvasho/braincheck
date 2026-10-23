import { formatDuration, useServerClock } from '@/hooks/use-server-clock';
import { useTranslation } from '@/hooks/use-translation';
import type { ExamCard } from '@/types';

/** Time limit, questions, attempts and window of an exam (list cards, intro page). */
export function ExamCardDetails({ exam }: { exam: ExamCard }) {
    const { t, formatDate } = useTranslation();

    const availability =
        exam.opens_at === null && exam.closes_at === null
            ? t('Always open')
            : `${exam.opens_at ? formatDate(exam.opens_at, 'PPp') : '…'} – ${
                  exam.closes_at ? formatDate(exam.closes_at, 'PPp') : '…'
              }`;

    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt className="text-muted-foreground">{t('Teacher')}</dt>
            <dd>{exam.teacher}</dd>
            {exam.groups.length > 0 && (
                <>
                    <dt className="text-muted-foreground">{t('Groups')}</dt>
                    <dd>{exam.groups.join(', ')}</dd>
                </>
            )}
            <dt className="text-muted-foreground">{t('Time limit')}</dt>
            <dd className="tabular">
                {exam.time_limit_minutes
                    ? t(':minutes min', { minutes: exam.time_limit_minutes })
                    : t('No limit')}
            </dd>
            <dt className="text-muted-foreground">{t('Questions')}</dt>
            <dd className="tabular">{exam.question_count}</dd>
            <dt className="text-muted-foreground">{t('Attempts')}</dt>
            <dd className="tabular">
                {exam.max_attempts === null
                    ? t(':used used (unlimited)', { used: exam.attempts_used })
                    : t(':used of :max used', {
                          used: exam.attempts_used,
                          max: exam.max_attempts,
                      })}
            </dd>
            <dt className="text-muted-foreground">{t('Availability')}</dt>
            <dd>{availability}</dd>
        </dl>
    );
}

/** "in 2:14:05" until $target on the server's clock. */
export function Countdown({
    target,
    serverNow,
    label,
}: {
    target: string;
    serverNow: string;
    label: 'opens' | 'left';
}) {
    const { t } = useTranslation();
    const now = useServerClock(serverNow);
    const seconds = Math.max(
        0,
        Math.floor((new Date(target).getTime() - now) / 1000),
    );
    const time = formatDuration(seconds);

    return (
        <span className="tabular text-sm font-medium" role="timer">
            {label === 'opens'
                ? t('Opens in :time', { time })
                : t(':time left', { time })}
        </span>
    );
}
