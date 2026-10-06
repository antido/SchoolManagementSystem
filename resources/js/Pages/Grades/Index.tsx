import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { ClassSubject } from '@/Types/academics';
import { Head, Link } from '@inertiajs/react';

/** The class subjects whose grades this person manages. */
export default function Index({
    classSubjects,
}: {
    classSubjects: Paginator<ClassSubject & { graded_count: number; average: number | null }>;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Grades" />
            <PageHeader title="Grades" intro="Open a subject to compute grades and see each student's grade." />

            <Card flush>
                {classSubjects.data.length === 0 ? (
                    <EmptyState icon="chart" title="No subjects" message="Subjects you teach appear here." />
                ) : (
                    <Table caption="Subjects">
                        <thead>
                            <tr>
                                <Th>Subject</Th>
                                <Th>Class</Th>
                                <Th>Term</Th>
                                <Th className="text-right">Graded</Th>
                                <Th className="text-right">Average</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {classSubjects.data.map((classSubject) => (
                                <tr key={classSubject.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <Link
                                            href={route('class-subjects.show', classSubject.id)}
                                            className="font-semibold hover:text-brand hover:underline"
                                        >
                                            {classSubject.subject.name}
                                        </Link>
                                    </Td>
                                    <Td className="text-muted">{classSubject.school_class.name}</Td>
                                    <Td className="text-muted">{classSubject.term.name}</Td>
                                    <Td className="text-right tabular-nums">{classSubject.graded_count}</Td>
                                    <Td className="text-right tabular-nums">
                                        {classSubject.average !== null ? `${classSubject.average}%` : <span className="text-muted">—</span>}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
                <Pagination paginator={classSubjects} />
            </Card>
        </AuthenticatedLayout>
    );
}
