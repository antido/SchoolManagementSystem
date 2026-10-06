import ActivityList from '@/Components/Activities/ActivityList';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import DetailList from '@/Components/DetailList';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ClassSubject } from '@/Types/academics';
import { ActivitySummary } from '@/Types/activities';
import { Head, Link } from '@inertiajs/react';
import GradesCard, { GradesCardData } from './Partials/GradesCard';

export default function Show({
    classSubject,
    activities,
    grades,
    can,
}: {
    classSubject: ClassSubject;
    activities: ActivitySummary[];
    /** Set for people who may see everyone's grades. */
    grades?: GradesCardData;
    can: { update: boolean; createActivity: boolean };
}) {
    const addButton = can.createActivity && (
        <Button small href={route('activities.create', { class_subject: classSubject.id })}>
            New activity
        </Button>
    );

    const title = `${classSubject.subject.name}, ${classSubject.school_class.name}`;

    return (
        <AuthenticatedLayout>
            <Head title={title} />

            <PageHeader
                title={classSubject.subject.name}
                intro={`${classSubject.school_class.name}, ${classSubject.term.name} (${classSubject.academic_year})`}
                breadcrumbs={[
                    { label: 'Classes', href: route('classes.index') },
                    { label: classSubject.school_class.name, href: route('classes.show', classSubject.school_class.id) },
                    { label: classSubject.subject.name },
                ]}
            />

            <div className="space-y-6">
                <Card>
                    <DetailList
                        items={[
                            {
                                label: 'Subject',
                                value: (
                                    <Link href={route('subjects.show', classSubject.subject.id)} className="link">
                                        {classSubject.subject.name} ({classSubject.subject.code})
                                    </Link>
                                ),
                            },
                            {
                                label: 'Class',
                                value: (
                                    <Link href={route('classes.show', classSubject.school_class.id)} className="link">
                                        {classSubject.school_class.name}
                                    </Link>
                                ),
                            },
                            { label: 'Term', value: classSubject.term.name },
                            { label: 'Teacher', value: classSubject.teacher?.name ?? 'Not assigned' },
                            { label: 'Status', value: <StatusBadge status={classSubject.status} /> },
                        ]}
                    />
                </Card>

                <Card title="Learning activities" actions={addButton} flush>
                    {activities.length === 0 ? (
                        <EmptyState
                            icon="clipboard"
                            title="No activities yet"
                            message="Assignments, quizzes and exams for this subject will be listed here."
                        />
                    ) : (
                        <ActivityList activities={activities} showClass={false} />
                    )}
                </Card>

                {grades && <GradesCard classSubjectId={classSubject.id} grades={grades} />}
            </div>
        </AuthenticatedLayout>
    );
}
