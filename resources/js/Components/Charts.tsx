import Table, { Td, Th } from '@/Components/Table';
import { ReactNode, useId, useRef, useState } from 'react';

/*
 * Small SVG charts for the analytics page, following one set of rules:
 * thin marks (bars at most 24px, 4px rounded ends square at the baseline,
 * 2px lines, 8px markers with a surface ring), hairline gridlines, labels in
 * text colours (never the series colour), a hover tooltip on every mark, and
 * a table view of the same numbers.
 */

/** The single-series hue. */
export const SERIES_BLUE = '#2a78d6';

/*
 * One hue, light to dark, for ordered bands (e.g. grade descriptors). These
 * five steps were checked with the palette validator against the white
 * surface: each is visibly lighter than the next, and the lightest still
 * clears 2:1. More than five steps of one hue cannot be told apart.
 */
const ORDINAL_RAMP = ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#0d366b'];

/**
 * n steps of the ramp, lightest first, spread across it. Past five bands the
 * steps would blur together, so every band takes the series hue and the
 * axis labels carry the order.
 */
export function ordinalColors(count: number): string[] {
    if (count <= 1 || count > ORDINAL_RAMP.length) {
        return Array(Math.max(count, 1)).fill(SERIES_BLUE);
    }

    return Array.from({ length: count }, (_, index) => ORDINAL_RAMP[Math.round((index * (ORDINAL_RAMP.length - 1)) / (count - 1))]);
}

/** A chart card body: the chart, with a button to show the numbers as a table instead. */
export function ChartFrame({
    title,
    description,
    table,
    children,
}: {
    title: string;
    description?: string;
    table: { columns: string[]; rows: (string | number)[][] };
    children: ReactNode;
}) {
    const [asTable, setAsTable] = useState(false);

    return (
        <section className="rounded-xl border border-line bg-surface" aria-label={title}>
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h2 className="text-base font-semibold">{title}</h2>
                    {description && <p className="text-sm text-muted">{description}</p>}
                </div>
                <button type="button" onClick={() => setAsTable(!asTable)} className="text-sm font-medium text-brand hover:underline" aria-pressed={asTable}>
                    {asTable ? 'Show chart' : 'Show table'}
                </button>
            </header>
            {asTable ? (
                <Table caption={title}>
                    <thead>
                        <tr>
                            {table.columns.map((column, index) => (
                                <Th key={column} className={index > 0 ? 'text-right' : undefined}>
                                    {column}
                                </Th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {table.rows.map((row, index) => (
                            <tr key={index}>
                                {row.map((value, column) => (
                                    <Td key={column} className={column > 0 ? 'text-right tabular-nums' : undefined}>
                                        {value}
                                    </Td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </Table>
            ) : (
                <div className="p-5">{children}</div>
            )}
        </section>
    );
}

interface TooltipState {
    x: number;
    y: number;
    title: string;
    value: string;
}

function Tooltip({ tip }: { tip: TooltipState | null }) {
    if (!tip) {
        return null;
    }

    return (
        <div
            role="status"
            className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-line bg-surface px-3 py-2 text-xs shadow-md"
            style={{ left: tip.x, top: tip.y - 8 }}
        >
            <p className="text-muted">{tip.title}</p>
            <p className="font-semibold text-ink tabular-nums">{tip.value}</p>
        </div>
    );
}

/** A clean top for the y axis: 10, 20, 50, 100, 200... */
function niceMax(value: number): number {
    if (value <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));

    return [1, 2, 5, 10].map((step) => step * magnitude).find((candidate) => candidate >= value) ?? 10 * magnitude;
}

/** Vertical columns from one baseline, with the value on each cap. */
export function ColumnChart({
    data,
    colors,
    format = (value) => String(value),
    height = 220,
}: {
    data: { label: string; value: number }[];
    colors?: string[];
    format?: (value: number) => string;
    height?: number;
}) {
    const [tip, setTip] = useState<TooltipState | null>(null);
    const box = useRef<HTMLDivElement>(null);
    const width = 560;
    const top = 20;
    const bottom = 36;
    const left = 36;
    const plot = height - top - bottom;
    const max = niceMax(Math.max(...data.map((d) => d.value), 0));
    const band = (width - left) / Math.max(data.length, 1);
    const barWidth = Math.min(24, band * 0.6);
    const ticks = [0, max / 2, max];

    const show = (index: number) => {
        const d = data[index];
        const svg = box.current?.querySelector('svg');
        const scale = svg ? svg.getBoundingClientRect().width / width : 1;
        const x = (left + band * index + band / 2) * scale;
        const y = (top + plot - (d.value / max) * plot) * scale;
        setTip({ x, y, title: d.label, value: format(d.value) });
    };

    return (
        <div ref={box} className="relative" onMouseLeave={() => setTip(null)}>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-auto w-full" role="img" aria-label={data.map((d) => `${d.label}: ${format(d.value)}`).join('; ')}>
                {ticks.map((tick) => {
                    const y = top + plot - (tick / max) * plot;

                    return (
                        <g key={tick}>
                            <line x1={left} x2={width} y1={y} y2={y} stroke="var(--color-line)" strokeWidth={1} />
                            <text x={left - 6} y={y + 4} textAnchor="end" className="fill-muted text-[11px] tabular-nums">
                                {format(tick)}
                            </text>
                        </g>
                    );
                })}
                {data.map((d, index) => {
                    const x = left + band * index + (band - barWidth) / 2;
                    const barHeight = (d.value / max) * plot;
                    const y = top + plot - barHeight;
                    const r = Math.min(4, barHeight);

                    return (
                        <g key={d.label}>
                            {barHeight > 0 && (
                                <path
                                    d={`M${x},${top + plot} V${y + r} Q${x},${y} ${x + r},${y} H${x + barWidth - r} Q${x + barWidth},${y} ${x + barWidth},${y + r} V${top + plot} Z`}
                                    fill={colors?.[index] ?? SERIES_BLUE}
                                />
                            )}
                            <text x={x + barWidth / 2} y={y - 6} textAnchor="middle" className="fill-ink text-[11px] font-semibold tabular-nums">
                                {format(d.value)}
                            </text>
                            <text x={x + barWidth / 2} y={height - 16} textAnchor="middle" className="fill-muted text-[10px]">
                                {d.label.length > 14 ? `${d.label.slice(0, 13)}…` : d.label}
                            </text>
                            {/* The hover target is the whole band, not just the bar. */}
                            <rect
                                x={left + band * index}
                                y={top}
                                width={band}
                                height={plot + bottom}
                                fill="transparent"
                                tabIndex={0}
                                aria-label={`${d.label}: ${format(d.value)}`}
                                onMouseEnter={() => show(index)}
                                onFocus={() => show(index)}
                                onBlur={() => setTip(null)}
                            />
                        </g>
                    );
                })}
                <line x1={left} x2={width} y1={top + plot} y2={top + plot} stroke="#c3c2b7" strokeWidth={1} />
            </svg>
            <Tooltip tip={tip} />
        </div>
    );
}

/**
 * One line over time with a crosshair tooltip. Missing values leave a gap.
 * An optional reference line (e.g. a target) is drawn as a labelled hairline.
 */
export function LineChart({
    points,
    max = 100,
    format = (value) => `${value}%`,
    reference,
    height = 220,
}: {
    points: { label: string; value: number | null }[];
    max?: number;
    format?: (value: number) => string;
    reference?: { value: number; label: string };
    height?: number;
}) {
    const [active, setActive] = useState<number | null>(null);
    const box = useRef<HTMLDivElement>(null);
    const clipId = useId();
    const width = 560;
    const top = 16;
    const bottom = 30;
    const left = 36;
    const right = 44;
    const plot = height - top - bottom;
    const step = (width - left - right) / Math.max(points.length - 1, 1);
    const xAt = (index: number) => left + step * index;
    const yAt = (value: number) => top + plot - (value / max) * plot;

    // Split into runs of consecutive known values, so gaps stay gaps.
    const runs: { index: number; value: number }[][] = [];
    points.forEach((point, index) => {
        if (point.value === null) {
            return;
        }
        const last = runs.at(-1);
        if (last && last.at(-1)!.index === index - 1) {
            last.push({ index, value: point.value });
        } else {
            runs.push([{ index, value: point.value }]);
        }
    });

    const lastKnown = [...points].map((point, index) => ({ ...point, index })).reverse().find((point) => point.value !== null);

    const pick = (clientX: number) => {
        const svg = box.current?.querySelector('svg');
        if (!svg) {
            return;
        }
        const rect = svg.getBoundingClientRect();
        const x = ((clientX - rect.left) / rect.width) * width;
        setActive(Math.max(0, Math.min(points.length - 1, Math.round((x - left) / step))));
    };

    const scale = () => {
        const svg = box.current?.querySelector('svg');
        return svg ? svg.getBoundingClientRect().width / width : 1;
    };

    const activePoint = active !== null ? points[active] : null;

    return (
        <div
            ref={box}
            className="relative"
            onMouseMove={(e) => pick(e.clientX)}
            onMouseLeave={() => setActive(null)}
        >
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-auto w-full outline-none focus-visible:ring-2 focus-visible:ring-pencil"
                role="img"
                tabIndex={0}
                aria-label={points.map((point) => `${point.label}: ${point.value === null ? 'no data' : format(point.value)}`).join('; ')}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowRight') setActive(Math.min(points.length - 1, (active ?? -1) + 1));
                    if (e.key === 'ArrowLeft') setActive(Math.max(0, (active ?? points.length) - 1));
                }}
                onBlur={() => setActive(null)}
            >
                <clipPath id={clipId}>
                    <rect x={left - 6} y={0} width={width} height={top + plot + 6} />
                </clipPath>
                {[0, max / 2, max].map((tick) => (
                    <g key={tick}>
                        <line x1={left} x2={width - right} y1={yAt(tick)} y2={yAt(tick)} stroke="var(--color-line)" strokeWidth={1} />
                        <text x={left - 6} y={yAt(tick) + 4} textAnchor="end" className="fill-muted text-[11px] tabular-nums">
                            {format(tick)}
                        </text>
                    </g>
                ))}
                {reference && (
                    <g>
                        <line x1={left} x2={width - right} y1={yAt(reference.value)} y2={yAt(reference.value)} stroke="#898781" strokeWidth={1} />
                        <text x={width - right + 4} y={yAt(reference.value) + 4} className="fill-muted text-[10px]">
                            {reference.label}
                        </text>
                    </g>
                )}
                {points.map((point, index) =>
                    index % Math.ceil(points.length / 6) === 0 || index === points.length - 1 ? (
                        <text key={point.label} x={xAt(index)} y={height - 10} textAnchor="middle" className="fill-muted text-[10px]">
                            {point.label}
                        </text>
                    ) : null,
                )}
                <g clipPath={`url(#${clipId})`}>
                    {runs.map((run, index) =>
                        run.length > 1 ? (
                            <polyline
                                key={index}
                                points={run.map((p) => `${xAt(p.index)},${yAt(p.value)}`).join(' ')}
                                fill="none"
                                stroke={SERIES_BLUE}
                                strokeWidth={2}
                                strokeLinejoin="round"
                                strokeLinecap="round"
                            />
                        ) : (
                            <circle key={index} cx={xAt(run[0].index)} cy={yAt(run[0].value)} r={4} fill={SERIES_BLUE} stroke="var(--color-surface)" strokeWidth={2} />
                        ),
                    )}
                </g>
                {lastKnown && lastKnown.value !== null && (
                    <g>
                        <circle cx={xAt(lastKnown.index)} cy={yAt(lastKnown.value)} r={4} fill={SERIES_BLUE} stroke="var(--color-surface)" strokeWidth={2} />
                        <text x={xAt(lastKnown.index) + 8} y={yAt(lastKnown.value) + 4} className="fill-ink text-[11px] font-semibold tabular-nums">
                            {format(lastKnown.value)}
                        </text>
                    </g>
                )}
                {activePoint && active !== null && (
                    <g>
                        <line x1={xAt(active)} x2={xAt(active)} y1={top} y2={top + plot} stroke="#c3c2b7" strokeWidth={1} />
                        {activePoint.value !== null && (
                            <circle cx={xAt(active)} cy={yAt(activePoint.value)} r={5} fill={SERIES_BLUE} stroke="var(--color-surface)" strokeWidth={2} />
                        )}
                    </g>
                )}
            </svg>
            {activePoint && active !== null && (
                <Tooltip
                    tip={{
                        x: xAt(active) * scale(),
                        y: (activePoint.value === null ? top + plot / 2 : yAt(activePoint.value)) * scale(),
                        title: activePoint.label,
                        value: activePoint.value === null ? 'No attendance recorded' : format(activePoint.value),
                    }}
                />
            )}
        </div>
    );
}

/** Horizontal bars, longest first, with the value at each tip. Good for long category names. */
export function BarList({
    data,
    max,
    format = (value) => String(value),
}: {
    data: { label: string; value: number }[];
    max?: number;
    format?: (value: number) => string;
}) {
    const top = max ?? niceMax(Math.max(...data.map((d) => d.value), 0));

    return (
        <ul className="space-y-3">
            {data.map((d) => (
                <li key={d.label} className="grid grid-cols-[minmax(0,10rem)_1fr] items-center gap-3 text-sm" title={`${d.label}: ${format(d.value)}`}>
                    <span className="truncate text-muted">{d.label}</span>
                    <span className="flex items-center gap-2">
                        <span
                            className="h-4 max-w-full shrink-0 rounded-r"
                            style={{ width: `calc(${(d.value / top) * 100}% - 3.5rem)`, minWidth: d.value > 0 ? 2 : 0, backgroundColor: SERIES_BLUE }}
                        />
                        <span className="font-semibold text-ink tabular-nums">{format(d.value)}</span>
                    </span>
                </li>
            ))}
        </ul>
    );
}
