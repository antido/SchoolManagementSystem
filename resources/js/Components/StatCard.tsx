import Icon, { IconName } from '@/Components/Icon';
import { Link } from '@inertiajs/react';

/**
 * One number on a dashboard, as its own small description list (label and
 * value). With `href` the whole card links to the page behind the number.
 */
export default function StatCard({
    label,
    value,
    hint,
    icon,
    href,
}: {
    label: string;
    value: string | number;
    hint?: string;
    icon?: IconName;
    href?: string | null;
}) {
    return (
        <div className="relative flex items-start gap-4 rounded-xl border border-line bg-surface p-5 has-[a:hover]:border-brand">
            {icon && (
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
                    <Icon name={icon} />
                </span>
            )}
            <dl className="min-w-0">
                <dt className="text-sm font-medium text-muted">{label}</dt>
                <dd className="mt-1 font-display text-2xl font-semibold tabular-nums">
                    {href ? (
                        <Link href={href} className="after:absolute after:inset-0 after:rounded-xl">
                            <span className="sr-only">{label}: </span>
                            {value}
                        </Link>
                    ) : (
                        value
                    )}
                </dd>
                {hint && <dd className="mt-0.5 text-xs text-muted">{hint}</dd>}
            </dl>
        </div>
    );
}
