import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import GradeBreakdown from '@/Components/GradeBreakdown';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ClassSubject } from '@/Types/academics';
import { GradingSystem, StudentGrade } from '@/Types/grades';
import { formatDateTime } from '@/Utils/format';
import { Head } from '@inertiajs/react';

/** One student's grade in a class subject: how it is made up, and every earlier value. */
export default function Student({
    classSubject,
    student,
    history,
    system,
    passing,
}: {
    classSubject: ClassSubject;
    student: { id: number; full_name: string; student_number: string };
    history: StudentGrade[];
    system: GradingSystem | null;
    passing: number | null;
}) {
    const [current, ...earlier] = history;
    const title = `${student.full_name}: ${classSubject.subject.name}`;

    return (
        <AuthenticatedLayout>
            <Head title={title} />

            <PageHeader
                title={title}
                intro={`${classSubject.school_class.name}, ${classSubject.term.name}`}
                breadcrumbs={[
                    { label: 'Grades', href: route('grades.index') },
                    { label: classSubject.subject.name, href: route('class-subjects.show', classSubject.id) },
                    { label: student.full_name },
                ]}
            />

            {!current ? (
                <Card>
                    <EmptyState icon="chart" title="No grade yet" message="A grade appears once the teacher computes grades for this subject." />
                </Card>
            ) : (
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2" title="How the grade is made up">
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <p className="font-display text-4xl font-semibold tabular-nums">{current.percentage}%</p>
                            <div>
                                <p className="font-medium">{current.descriptor}</p>
                                <Badge tone={current.passed ? 'success' : 'danger'}>{current.passed ? 'Passed' : 'Failed'}</Badge>
                            </div>
                        </div>
                        <GradeBreakdown components={current.breakdown} />
                        <p className="mt-4 text-xs text-muted">
                            Computed {formatDateTime(current.computed_at)}
                            {system && ` with ${system.name}`}
                            {passing !== null && `; ${passing}% passes`}. Components with nothing to count yet are left out and the other weights
                            scaled up.
                        </p>
                    </Card>

                    <Card title="History" flush>
                        {earlier.length === 0 ? (
                            <p className="px-5 py-4 text-sm text-muted">No earlier grades.</p>
                        ) : (
                            <ol className="divide-y divide-line">
                                {earlier.map((grade) => (
                                    <li key={grade.id} className="flex items-baseline justify-between gap-3 px-5 py-3">
                                        <span className="text-sm text-muted">{formatDateTime(grade.computed_at)}</span>
                                        <span className="font-display font-semibold tabular-nums">{grade.percentage}%</span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Card>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
