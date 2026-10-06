import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import PageHeader from '@/Components/PageHeader';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { GradingScale, GradingSystem } from '@/Types/grades';
import { humanize } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

/** School-wide grading settings: how activities are weighed, and how percentages are described. */
export default function Index({ systems, scales }: { systems: GradingSystem[]; scales: GradingScale[] }) {
    const [deleting, setDeleting] = useState<{ name: string; url: string } | null>(null);
    const { busy, send } = useRequest();

    return (
        <AuthenticatedLayout>
            <Head title="Grading" />
            <PageHeader
                title="Grading"
                intro="Grades are the weighted average of activity scores. Teachers compute them per subject; changes here apply the next time they do."
            />

            <div className="space-y-6">
                <Card title="Grading systems" actions={<Button small href={route('grading.systems.create')}>Add grading system</Button>} flush>
                    <ul className="divide-y divide-line">
                        {systems.map((system) => (
                            <li key={system.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start">
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-semibold">
                                        {system.name}
                                        {system.is_default && <Badge tone="brand">Default</Badge>}
                                    </p>
                                    <ul className="mt-2 space-y-1 text-sm">
                                        {system.components.map((component) => (
                                            <li key={component.name} className="flex gap-3">
                                                <span className="w-12 text-right font-semibold tabular-nums">{component.weight}%</span>
                                                <span>
                                                    {component.name}
                                                    <span className="text-muted"> ({component.activity_types.map(humanize).join(', ')})</span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="mt-2 text-xs text-muted">
                                        Scale: {system.scale.name}. Chosen by {system.class_subjects_count}{' '}
                                        {system.class_subjects_count === 1 ? 'subject' : 'subjects'}.
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button small variant="secondary" href={route('grading.systems.edit', system.id)}>
                                        Edit<span className="sr-only"> {system.name}</span>
                                    </Button>
                                    {system.can_delete && (
                                        <Button
                                            small
                                            variant="ghost"
                                            className="text-danger"
                                            onClick={() => setDeleting({ name: system.name, url: route('grading.systems.destroy', system.id) })}
                                        >
                                            Delete<span className="sr-only"> {system.name}</span>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title="Grading scales" actions={<Button small href={route('grading.scales.create')}>Add grading scale</Button>} flush>
                    <ul className="divide-y divide-line">
                        {scales.map((scale) => (
                            <li key={scale.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start">
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-semibold">
                                        {scale.name}
                                        {scale.is_default && <Badge tone="brand">Default</Badge>}
                                    </p>
                                    <p className="mt-1 text-sm text-muted">Passing: {scale.passing_percentage}%</p>
                                    <ul className="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                                        {scale.bands.map((band) => (
                                            <li key={band.min_percentage} className="flex gap-3">
                                                <span className="w-16 text-right tabular-nums text-muted">{band.min_percentage}% +</span>
                                                <span>{band.label}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                                <div className="flex gap-2">
                                    <Button small variant="secondary" href={route('grading.scales.edit', scale.id)}>
                                        Edit<span className="sr-only"> {scale.name}</span>
                                    </Button>
                                    {scale.can_delete && (
                                        <Button
                                            small
                                            variant="ghost"
                                            className="text-danger"
                                            onClick={() => setDeleting({ name: scale.name, url: route('grading.scales.destroy', scale.id) })}
                                        >
                                            Delete<span className="sr-only"> {scale.name}</span>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <ConfirmDialog
                show={deleting !== null}
                title={`Delete ${deleting?.name}?`}
                message="This cannot be undone."
                confirmLabel="Delete"
                processing={busy}
                onConfirm={() => deleting && send('delete', deleting.url, {}, { onSuccess: () => setDeleting(null) })}
                onClose={() => setDeleting(null)}
            />
        </AuthenticatedLayout>
    );
}
