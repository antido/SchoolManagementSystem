import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ClassSubject } from '@/Types/academics';
import { StudentGrade } from '@/Types/grades';
import { formatDate } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

/** A student's current grade in every subject. */
export default function Mine({
    grades,
    studentId,
}: {
    grades: (StudentGrade & { class_subject: ClassSubject })[];
    studentId: number;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="My grades" />
            <PageHeader title="My grades" intro="Your latest grade in each subject. Open a subject to see how it is made up." />

            <Card flush>
                {grades.length === 0 ? (
                    <EmptyState icon="chart" title="No grades yet" message="Grades appear here once your teachers compute them." />
                ) : (
                    <Table caption="My grades">
                        <thead>
                            <tr>
                                <Th>Subject</Th>
                                <Th>Term</Th>
                                <Th className="text-right">Grade</Th>
                                <Th>Descriptor</Th>
                                <Th>Remarks</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {grades.map((grade) => (
                                <tr key={grade.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <Link
                                            href={route('grades.student', [grade.class_subject.id, studentId])}
                                            className="font-semibold hover:text-brand hover:underline"
                                        >
                                            {grade.class_subject.subject.name}
                                        </Link>
                                        <span className="block text-xs text-muted">{grade.class_subject.school_class.name}</span>
                                    </Td>
                                    <Td className="text-muted">{grade.class_subject.term.name}</Td>
                                    <Td className="text-right font-display font-semibold tabular-nums">{grade.percentage}%</Td>
                                    <Td className="text-muted">
                                        {grade.descriptor}
                                        <span className="block text-xs">as of {formatDate(grade.computed_at)}</span>
                                    </Td>
                                    <Td>
                                        <Badge tone={grade.passed ? 'success' : 'danger'}>{grade.passed ? 'Passed' : 'Failed'}</Badge>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
