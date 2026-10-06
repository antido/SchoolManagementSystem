import { GradeComponentResult } from '@/Types/grades';

/**
 * How a grade was made up: each component's weight, percentage and the
 * points behind it. Components with nothing to count yet are marked so.
 */
export default function GradeBreakdown({ components }: { components: GradeComponentResult[] }) {
    return (
        <dl className="divide-y divide-line">
            {components.map((component) => (
                <div key={component.name} className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                    <dt>
                        <span className="font-medium">{component.name}</span>
                        <span className="ml-2 text-xs text-muted">{component.weight}% of the grade</span>
                    </dt>
                    <dd className="text-right">
                        {component.percentage === null ? (
                            <span className="text-sm text-muted">Nothing to count yet</span>
                        ) : (
                            <>
                                <span className="font-display font-semibold tabular-nums">{component.percentage}%</span>
                                <span className="ml-2 text-xs text-muted tabular-nums">
                                    {component.earned} / {component.possible} points, {component.count}{' '}
                                    {component.count === 1 ? 'activity' : 'activities'}
                                </span>
                            </>
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
