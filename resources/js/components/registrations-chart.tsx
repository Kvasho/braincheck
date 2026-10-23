import { useReducedMotion } from 'motion/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { useTranslation } from '@/hooks/use-translation';

type Day = { date: string; count: number };

/**
 * Daily registrations, single series (no legend: the card title names it).
 * Colours come from theme tokens only. A visually hidden table carries the
 * same data for screen readers; animation is off under reduced motion.
 */
export function RegistrationsChart({ days }: { days: Day[] }) {
    const { t, formatDate } = useTranslation();
    const reduceMotion = useReducedMotion();
    const total = days.reduce((sum, day) => sum + day.count, 0);

    return (
        <figure className="space-y-2">
            <div
                className="h-56 w-full"
                role="img"
                aria-label={t(
                    ':total registrations in the last 30 days, shown per day.',
                    { total },
                )}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={days}
                        margin={{ top: 8, right: 8, bottom: 0, left: -16 }}
                        barCategoryGap={2}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                        />
                        <XAxis
                            dataKey="date"
                            tickFormatter={(value: string) =>
                                formatDate(value, 'd MMM')
                            }
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            minTickGap={24}
                        />
                        <YAxis
                            allowDecimals={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                            tickLine={false}
                            axisLine={false}
                            width={40}
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                            contentStyle={{
                                background: 'var(--popover)',
                                color: 'var(--popover-foreground)',
                                border: '1px solid var(--border)',
                                borderRadius: 8,
                            }}
                            labelFormatter={(value) =>
                                typeof value === 'string'
                                    ? formatDate(value, 'PP')
                                    : ''
                            }
                            formatter={(value) => [
                                String(value),
                                t('Registrations'),
                            ]}
                        />
                        <Bar
                            dataKey="count"
                            fill="var(--primary)"
                            radius={[4, 4, 0, 0]}
                            isAnimationActive={!reduceMotion}
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>

            <table className="sr-only">
                <caption>{t('Registrations per day')}</caption>
                <thead>
                    <tr>
                        <th scope="col">{t('Date')}</th>
                        <th scope="col">{t('Registrations')}</th>
                    </tr>
                </thead>
                <tbody>
                    {days.map((day) => (
                        <tr key={day.date}>
                            <td>{formatDate(day.date, 'PP')}</td>
                            <td>{day.count}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
