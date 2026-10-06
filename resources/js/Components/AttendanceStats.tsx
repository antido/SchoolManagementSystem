export interface AttendanceSummary {
    present: number;
    absent: number;
    late: number;
    excused: number;
    total: number;
    percentage: number | null;
}

/** The attendance percentage with the counts behind it. */
export default function AttendanceStats({ summary }: { summary: AttendanceSummary }) {
    const items = [
        { label: 'Present', value: summary.present, className: 'text-success' },
        { label: 'Late', value: summary.late, className: 'text-warning' },
        { label: 'Absent', value: summary.absent, className: 'text-danger' },
        { label: 'Excused', value: summary.excused, className: 'text-brand' },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-center">
            <div>
                <p className="font-display text-4xl font-semibold tabular-nums">
                    {summary.percentage === null ? '—' : `${summary.percentage}%`}
                </p>
                <p className="text-sm text-muted">attendance</p>
            </div>
            <dl className="grid grid-cols-4 gap-2 sm:ml-6">
                {items.map((item) => (
                    <div key={item.label} className="rounded-lg border border-line px-3 py-2 text-center">
                        <dt className="text-xs text-muted">{item.label}</dt>
                        <dd className={`font-display text-lg font-semibold tabular-nums ${item.className}`}>{item.value}</dd>
                    </div>
                ))}
            </dl>
            <p className="text-xs text-muted sm:col-span-2">
                Present and late days count as attended. Excused days are left out of the percentage.
            </p>
        </div>
    );
}
