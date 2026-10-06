import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DetailList from '@/Components/DetailList';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AcademicTerm, AcademicYear } from '@/Types/academics';
import { formatDate } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import TermFormModal from './Partials/TermFormModal';

type Confirming =
    | { kind: 'deleteYear' }
    | { kind: 'deleteTerm'; term: AcademicTerm }
    | { kind: 'activateTerm'; term: AcademicTerm }
    | { kind: 'toggleYear' }
    | null;

export default function Show({
    year,
    terms,
    can,
}: {
    year: AcademicYear;
    terms: AcademicTerm[];
    can: { update: boolean; delete: boolean; createTerm: boolean; updateTerms: boolean };
}) {
    const [termForm, setTermForm] = useState<{ open: boolean; term: AcademicTerm | null }>({ open: false, term: null });
    const [confirming, setConfirming] = useState<Confirming>(null);
    const { busy, send } = useRequest();
    const close = () => setConfirming(null);

    const confirm = () => {
        const options = { onSuccess: close };

        if (confirming?.kind === 'deleteYear') {
            send('delete', route('academic-years.destroy', year.id), {}, options);
        } else if (confirming?.kind === 'deleteTerm') {
            send('delete', route('academic-terms.destroy', confirming.term.id), {}, options);
        } else if (confirming?.kind === 'activateTerm') {
            send('patch', route('academic-terms.activate', confirming.term.id), {}, options);
        } else if (confirming?.kind === 'toggleYear') {
            send('patch', route('academic-years.activate', year.id), { active: !year.is_active }, options);
        }
    };

    const dialog = (() => {
        switch (confirming?.kind) {
            case 'deleteYear':
                return { title: `Delete ${year.name}?`, message: 'This cannot be undone.', label: 'Delete year', tone: 'danger' as const };
            case 'deleteTerm':
                return { title: `Delete ${confirming.term.name}?`, message: 'This cannot be undone.', label: 'Delete term', tone: 'danger' as const };
            case 'activateTerm':
                return {
                    title: `Make ${confirming.term.name} the active term?`,
                    message: `${year.name} becomes the active year too. Any other active year and term stop being active.`,
                    label: 'Make active',
                    tone: 'primary' as const,
                };
            case 'toggleYear':
                return year.is_active
                    ? { title: `Stop ${year.name} being active?`, message: 'Its terms stop being active too.', label: 'Deactivate', tone: 'danger' as const }
                    : {
                          title: `Make ${year.name} the active year?`,
                          message: 'Any other active year and its terms stop being active.',
                          label: 'Make active',
                          tone: 'primary' as const,
                      };
            default:
                return null;
        }
    })();

    return (
        <AuthenticatedLayout>
            <Head title={year.name} />

            <PageHeader
                title={year.name}
                breadcrumbs={[{ label: 'Academic years', href: route('academic-years.index') }, { label: year.name }]}
                actions={
                    <>
                        {can.update && (
                            <Button variant="secondary" onClick={() => setConfirming({ kind: 'toggleYear' })}>
                                {year.is_active ? 'Deactivate' : 'Make active'}
                            </Button>
                        )}
                        {can.update && (
                            <Button variant="secondary" href={route('academic-years.edit', year.id)}>
                                Edit
                            </Button>
                        )}
                        {can.delete && (
                            <Button variant="danger" onClick={() => setConfirming({ kind: 'deleteYear' })}>
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <div className="space-y-6">
                <Card>
                    <DetailList
                        items={[
                            { label: 'First day', value: formatDate(year.start_date) },
                            { label: 'Last day', value: formatDate(year.end_date) },
                            {
                                label: 'Status',
                                value: year.is_active ? <Badge tone="success">Active year</Badge> : <Badge>Inactive</Badge>,
                            },
                        ]}
                    />
                </Card>

                <Card
                    title="Terms"
                    flush
                    actions={
                        can.createTerm && (
                            <Button small onClick={() => setTermForm({ open: true, term: null })}>
                                Add term
                            </Button>
                        )
                    }
                >
                    {terms.length === 0 ? (
                        <EmptyState
                            icon="calendar"
                            title="No terms yet"
                            message="Add the terms or semesters this year is divided into."
                        />
                    ) : (
                        <ul className="divide-y divide-line">
                            {terms.map((term) => (
                                <li key={term.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-center gap-2 font-semibold">
                                            {term.name}
                                            {term.is_active && <Badge tone="success">Active term</Badge>}
                                        </p>
                                        <p className="text-sm text-muted">
                                            {formatDate(term.start_date)} to {formatDate(term.end_date)}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {can.updateTerms && !term.is_active && (
                                            <Button small variant="secondary" onClick={() => setConfirming({ kind: 'activateTerm', term })}>
                                                Make active
                                            </Button>
                                        )}
                                        {can.updateTerms && (
                                            <Button small variant="secondary" onClick={() => setTermForm({ open: true, term })}>
                                                Edit
                                            </Button>
                                        )}
                                        {term.can_delete && (
                                            <Button small variant="secondary" onClick={() => setConfirming({ kind: 'deleteTerm', term })}>
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <TermFormModal
                show={termForm.open}
                year={year}
                term={termForm.term}
                onClose={() => setTermForm({ open: false, term: null })}
            />

            <ConfirmDialog
                show={dialog !== null}
                title={dialog?.title ?? ''}
                message={dialog?.message}
                confirmLabel={dialog?.label}
                tone={dialog?.tone}
                processing={busy}
                onConfirm={confirm}
                onClose={close}
            />
        </AuthenticatedLayout>
    );
}
