import { useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/*
 * Single-series charts (the card title names the series, so no legend).
 * Theme tokens only: one hue (--primary) for the data, recessive grid and
 * axes, text in muted ink. Hover tooltip on every mark; a visually hidden
 * table carries the same data for screen readers; no animation under
 * reduced motion.
 */

const axisTick = { fill: 'var(--muted-foreground)', fontSize: 12 };
const tooltipStyle = {
    background: 'var(--popover)',
    color: 'var(--popover-foreground)',
    border: '1px solid var(--border)',
    borderRadius: 8,
};

export type ChartPoint = {
    key: string;
    label: string;
    value: number;
    detail?: string;
};

function DataTable({
    caption,
    labelHeader,
    valueHeader,
    points,
    format,
}: {
    caption: string;
    labelHeader: string;
    valueHeader: string;
    points: ChartPoint[];
    format: (value: number) => string;
}) {
    return (
        <table className="sr-only">
            <caption>{caption}</caption>
            <thead>
                <tr>
                    <th scope="col">{labelHeader}</th>
                    <th scope="col">{valueHeader}</th>
                </tr>
            </thead>
            <tbody>
                {points.map((point) => (
                    <tr key={point.key}>
                        <td>
                            {point.detail
                                ? `${point.label} (${point.detail})`
                                : point.label}
                        </td>
                        <td>{format(point.value)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

type ChartProps = {
    points: ChartPoint[];
    /** Visible summary for assistive technology (role="img"). */
    summary: string;
    caption: string;
    labelHeader: string;
    valueHeader: string;
    format?: (value: number) => string;
    /** Fixed y domain, e.g. [0, 100] for percents. */
    domain?: [number, number];
    height?: string;
    empty?: ReactNode;
};

export function SeriesBarChart({
    points,
    summary,
    caption,
    labelHeader,
    valueHeader,
    format = (value) => String(value),
    domain,
    height = 'h-56',
    layout = 'vertical-bars',
    empty,
}: ChartProps & { layout?: 'vertical-bars' | 'horizontal-bars' }) {
    const reduceMotion = useReducedMotion();

    if (points.length === 0) {
        return <>{empty}</>;
    }

    const horizontal = layout === 'horizontal-bars';

    return (
        <figure className="space-y-2">
            <div className={`${height} w-full`} role="img" aria-label={summary}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={points}
                        layout={horizontal ? 'vertical' : 'horizontal'}
                        margin={{
                            top: 8,
                            right: 16,
                            bottom: 0,
                            left: horizontal ? 8 : -16,
                        }}
                        barCategoryGap={2}
                    >
                        <CartesianGrid
                            vertical={horizontal}
                            horizontal={!horizontal}
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                        />
                        {horizontal ? (
                            <>
                                <XAxis
                                    type="number"
                                    domain={domain}
                                    tick={axisTick}
                                    tickLine={false}
                                    axisLine={false}
                                    tickFormatter={format}
                                />
                                <YAxis
                                    type="category"
                                    dataKey="label"
                                    tick={axisTick}
                                    tickLine={false}
                                    axisLine={{ stroke: 'var(--border)' }}
                                    width={120}
                                />
                            </>
                        ) : (
                            <>
                                <XAxis
                                    dataKey="label"
                                    tick={axisTick}
                                    tickLine={false}
                                    axisLine={{ stroke: 'var(--border)' }}
                                    minTickGap={12}
                                />
                                <YAxis
                                    domain={domain}
                                    allowDecimals={false}
                                    tick={axisTick}
                                    tickLine={false}
                                    axisLine={false}
                                    width={40}
                                    tickFormatter={format}
                                />
                            </>
                        )}
                        <Tooltip
                            cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                            contentStyle={tooltipStyle}
                            formatter={(value) => [
                                format(Number(value)),
                                valueHeader,
                            ]}
                        />
                        <Bar
                            dataKey="value"
                            fill="var(--primary)"
                            radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
                            isAnimationActive={!reduceMotion}
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <DataTable
                caption={caption}
                labelHeader={labelHeader}
                valueHeader={valueHeader}
                points={points}
                format={format}
            />
        </figure>
    );
}

export function SeriesLineChart({
    points,
    summary,
    caption,
    labelHeader,
    valueHeader,
    format = (value) => String(value),
    domain,
    height = 'h-56',
    empty,
}: ChartProps) {
    const reduceMotion = useReducedMotion();

    if (points.length === 0) {
        return <>{empty}</>;
    }

    return (
        <figure className="space-y-2">
            <div className={`${height} w-full`} role="img" aria-label={summary}>
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={points}
                        margin={{ top: 8, right: 16, bottom: 0, left: -16 }}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                        />
                        <XAxis
                            dataKey="label"
                            tick={axisTick}
                            tickLine={false}
                            axisLine={{ stroke: 'var(--border)' }}
                            minTickGap={24}
                        />
                        <YAxis
                            domain={domain}
                            tick={axisTick}
                            tickLine={false}
                            axisLine={false}
                            width={40}
                            tickFormatter={format}
                        />
                        <Tooltip
                            cursor={{ stroke: 'var(--border)' }}
                            contentStyle={tooltipStyle}
                            formatter={(value) => [
                                format(Number(value)),
                                valueHeader,
                            ]}
                            labelFormatter={(_, payload) => {
                                const point = payload?.[0]?.payload as
                                    | ChartPoint
                                    | undefined;

                                return point
                                    ? point.detail
                                        ? `${point.label} · ${point.detail}`
                                        : point.label
                                    : '';
                            }}
                        />
                        <Line
                            type="monotone"
                            dataKey="value"
                            stroke="var(--primary)"
                            strokeWidth={2}
                            dot={{
                                r: 4,
                                fill: 'var(--primary)',
                                stroke: 'var(--card)',
                                strokeWidth: 2,
                            }}
                            activeDot={{ r: 6 }}
                            isAnimationActive={!reduceMotion}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
            <DataTable
                caption={caption}
                labelHeader={labelHeader}
                valueHeader={valueHeader}
                points={points}
                format={format}
            />
        </figure>
    );
}

/** A tiny trend line without axes (dashboard). The caller provides the accessible text. */
export function Sparkline({
    values,
    label,
}: {
    values: number[];
    label: string;
}) {
    const reduceMotion = useReducedMotion();

    if (values.length < 2) {
        return null;
    }

    return (
        <div className="h-12 w-full" role="img" aria-label={label}>
            <ResponsiveContainer width="100%" height="100%">
                <LineChart
                    data={values.map((value, index) => ({ index, value }))}
                    margin={{ top: 4, right: 4, bottom: 4, left: 4 }}
                >
                    <YAxis hide domain={[0, 100]} />
                    <Line
                        type="monotone"
                        dataKey="value"
                        stroke="var(--primary)"
                        strokeWidth={2}
                        dot={false}
                        isAnimationActive={!reduceMotion}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}
