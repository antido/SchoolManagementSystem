import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { SchoolClassSummary } from '@/Types/academics';
import { Head } from '@inertiajs/react';

/** The classes whose attendance this person can open, this academic year. */
export default function Index({ classes }: { classes: (SchoolClassSummary & { students_count: number; can_record: boolean })[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Attendance" />
            <PageHeader title="Attendance" intro="Take attendance for today, or look back over a period." />

            <Card flush>
                {classes.length === 0 ? (
                    <EmptyState icon="class" title="No classes this year" message="Classes you advise or teach appear here." />
                ) : (
                    <Table caption="Classes">
                        <thead>
                            <tr>
                                <Th>Class</Th>
                                <Th className="text-right">Students</Th>
                                <Th>
                                    <span className="sr-only">Actions</span>
                                </Th>
                            </tr>
                        </thead>
                        <tbody>
                            {classes.map((schoolClass) => (
                                <tr key={schoolClass.id}>
                                    <Td>
                                        <span className="font-semibold">{schoolClass.name}</span>
                                        <span className="block text-xs text-muted">{schoolClass.grade_level}</span>
                                    </Td>
                                    <Td className="text-right tabular-nums">{schoolClass.students_count}</Td>
                                    <Td>
                                        <div className="flex justify-end gap-2">
                                            <Button small variant={schoolClass.can_record ? 'primary' : 'secondary'} href={route('attendance.sheet', schoolClass.id)}>
                                                {schoolClass.can_record ? 'Take attendance' : 'View sheet'}
                                                <span className="sr-only"> for {schoolClass.name}</span>
                                            </Button>
                                            <Button small variant="ghost" href={route('attendance.summary', schoolClass.id)}>
                                                Summary<span className="sr-only"> for {schoolClass.name}</span>
                                            </Button>
                                        </div>
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
