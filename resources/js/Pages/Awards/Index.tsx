import AwardBadge from '@/Components/AwardBadge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import StudentAwardList from '@/Components/StudentAwardList';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Award, StudentAward } from '@/Types/awards';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    awards,
    myAwards,
    can,
}: {
    awards: Award[];
    /** Set for students. */
    myAwards: StudentAward[] | null;
    can: { create: boolean };
}) {
    const addButton = can.create && <Button href={route('awards.create')}>Add award</Button>;

    return (
        <AuthenticatedLayout>
            <Head title="Awards" />
            <PageHeader title="Awards" intro="The ways the school recognises its students." actions={addButton} />

            <div className="space-y-6">
                {myAwards && (
                    <Card title="My awards" flush>
                        {myAwards.length === 0 ? (
                            <EmptyState icon="award" title="No awards yet" message="Awards you receive are listed here." />
                        ) : (
                            <StudentAwardList awards={myAwards} />
                        )}
                    </Card>
                )}

                {awards.length === 0 ? (
                    <Card>
                        <EmptyState icon="award" title="No awards yet" message="Add the awards your school gives." action={addButton} />
                    </Card>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {awards.map((award) => (
                            <li key={award.id}>
                                <Link
                                    href={route('awards.show', award.id)}
                                    className="flex h-full gap-4 rounded-xl border border-line bg-surface p-5 hover:border-brand/40 hover:bg-brand-soft/30"
                                >
                                    <AwardBadge imageUrl={award.image_url} />
                                    <div className="min-w-0">
                                        <p className="font-display font-semibold">{award.name}</p>
                                        {award.description && <p className="mt-1 line-clamp-2 text-sm text-muted">{award.description}</p>}
                                        <p className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                                            {award.status !== 'active' && <StatusBadge status={award.status} />}
                                            Given {award.given_count} {award.given_count === 1 ? 'time' : 'times'}
                                        </p>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
