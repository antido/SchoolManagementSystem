import AwardBadge from '@/Components/AwardBadge';
import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import Textarea from '@/Components/Textarea';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Award, StudentAward } from '@/Types/awards';
import { cn, formatDate } from '@/Utils/format';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import GrantAwardModal, { AwardCandidate } from './Partials/GrantAwardModal';

type Recipient = StudentAward & { student: { id: number; full_name: string }; can_revoke: boolean };

export default function Show({
    award,
    recipients,
    academicYears,
    activeYearId,
    candidates,
    can,
}: {
    award: Award;
    recipients: Recipient[];
    academicYears: Option<number>[];
    activeYearId: number | null;
    candidates?: AwardCandidate[];
    can: { update: boolean; delete: boolean; grant: boolean };
}) {
    const [granting, setGranting] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [revoking, setRevoking] = useState<Recipient | null>(null);
    const [reason, setReason] = useState('');
    const { busy, send } = useRequest();
    const { errors } = usePage().props as { errors: Record<string, string> };

    return (
        <AuthenticatedLayout>
            <Head title={award.name} />

            <PageHeader
                title={award.name}
                breadcrumbs={[{ label: 'Awards', href: route('awards.index') }, { label: award.name }]}
                actions={
                    <>
                        {can.grant && <Button onClick={() => setGranting(true)}>Give award</Button>}
                        {can.update && (
                            <Button variant="secondary" href={route('awards.edit', award.id)}>
                                Edit
                            </Button>
                        )}
                        {can.delete && (
                            <Button variant="danger" onClick={() => setDeleting(true)}>
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <div className="space-y-6">
                <Card>
                    <div className="flex flex-col gap-5 sm:flex-row">
                        <AwardBadge imageUrl={award.image_url} className="size-24" />
                        <div className="min-w-0 space-y-3">
                            {award.status !== 'active' && <StatusBadge status={award.status} />}
                            {award.description && <p>{award.description}</p>}
                            {award.criteria && (
                                <div>
                                    <p className="text-sm font-semibold">Who receives it</p>
                                    <p className="whitespace-pre-line text-muted">{award.criteria}</p>
                                </div>
                            )}
                        </div>
                    </div>
                </Card>

                <Card title={`Received by (${recipients.filter((r) => !r.revoked_at).length})`} flush>
                    {recipients.length === 0 ? (
                        <EmptyState icon="award" title="Not given yet" />
                    ) : (
                        <Table caption="Recipients">
                            <thead>
                                <tr>
                                    <Th>Student</Th>
                                    <Th>Date</Th>
                                    <Th>For</Th>
                                    <Th>Given by</Th>
                                    <Th>
                                        <span className="sr-only">Actions</span>
                                    </Th>
                                </tr>
                            </thead>
                            <tbody>
                                {recipients.map((recipient) => (
                                    <tr key={recipient.id} className={cn(recipient.revoked_at && 'text-muted')}>
                                        <Td>
                                            <Link
                                                href={route('students.show', recipient.student.id)}
                                                className={cn('font-semibold hover:text-brand hover:underline', recipient.revoked_at && 'line-through')}
                                            >
                                                {recipient.student.full_name}
                                            </Link>
                                            {recipient.remarks && <span className="block text-xs text-muted">{recipient.remarks}</span>}
                                            {recipient.revoked_at && (
                                                <span className="mt-1 block text-xs text-danger">
                                                    <Badge tone="danger">Revoked</Badge> {recipient.revoked_reason}
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="whitespace-nowrap">{formatDate(recipient.awarded_on)}</Td>
                                        <Td className="text-muted">
                                            {[recipient.school_class?.name, recipient.academic_year].filter(Boolean).join(', ') || '—'}
                                        </Td>
                                        <Td className="text-muted">{recipient.awarded_by}</Td>
                                        <Td className="text-right">
                                            {recipient.can_revoke && (
                                                <Button small variant="ghost" className="text-danger" onClick={() => setRevoking(recipient)}>
                                                    Revoke<span className="sr-only"> from {recipient.student.full_name}</span>
                                                </Button>
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>
            </div>

            <GrantAwardModal
                show={granting}
                awardId={award.id}
                awardName={award.name}
                candidates={candidates}
                academicYears={academicYears}
                activeYearId={activeYearId}
                onClose={() => setGranting(false)}
            />

            <ConfirmDialog
                show={revoking !== null}
                title={`Revoke ${award.name} from ${revoking?.student.full_name}?`}
                message={
                    <div className="space-y-2">
                        <p>The award stays in the history, marked revoked with your reason.</p>
                        <label htmlFor="revoke-reason" className="block text-sm font-medium text-ink">
                            Reason
                        </label>
                        <Textarea id="revoke-reason" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} />
                        <InputError message={errors.reason} />
                    </div>
                }
                confirmLabel="Revoke award"
                processing={busy}
                onConfirm={() =>
                    revoking &&
                    send('patch', route('awards.revoke', revoking.id), { reason }, {
                        onSuccess: () => {
                            setRevoking(null);
                            setReason('');
                        },
                    })
                }
                onClose={() => setRevoking(null)}
            />

            <ConfirmDialog
                show={deleting}
                title={`Delete ${award.name}?`}
                message="This cannot be undone."
                confirmLabel="Delete award"
                processing={busy}
                onConfirm={() => send('delete', route('awards.destroy', award.id))}
                onClose={() => setDeleting(false)}
            />
        </AuthenticatedLayout>
    );
}
