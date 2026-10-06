import AttendanceStats, { AttendanceSummary } from '@/Components/AttendanceStats';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import PeriodFilter from '@/Components/PeriodFilter';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { formatDate } from '@/Utils/format';
import { Head } from '@inertiajs/react';

interface HistoryRow {
    id: number;
    date: string;
    status: string;
    remarks: string | null;
    class: string;
    subject: string | null;
}

/** One student's attendance: the totals and every recorded day. */
export default function Student({
    student,
    filters,
    summary,
    history,
    isOwn,
}: {
    student: { id: number; full_name: string; student_number: string };
    filters: { from: string; to: string };
    summary: AttendanceSummary;
    history: Paginator<HistoryRow>;
    isOwn: boolean;
}) {
    const title = isOwn ? 'My attendance' : `Attendance: ${student.full_name}`;

    return (
        <AuthenticatedLayout>
            <Head title={title} />

            <PageHeader
                title={title}
                intro={`${formatDate(filters.from)} to ${formatDate(filters.to)}`}
                breadcrumbs={
                    isOwn
                        ? undefined
                        : [
                              { label: 'Students', href: route('students.index') },
                              { label: student.full_name, href: route('students.show', student.id) },
                              { label: 'Attendance' },
                          ]
                }
            />

            <div className="space-y-6">
                <Card>
                    <AttendanceStats summary={summary} />
                </Card>

                <Card flush>
                    <PeriodFilter url={route('attendance.student', student.id)} filters={filters} />

                    {history.data.length === 0 ? (
                        <EmptyState icon="calendar" title="No attendance recorded in this period" />
                    ) : (
                        <Table caption="Attendance history">
                            <thead>
                                <tr>
                                    <Th>Day</Th>
                                    <Th>For</Th>
                                    <Th>Status</Th>
                                    <Th>Remarks</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.data.map((row) => (
                                    <tr key={row.id}>
                                        <Td className="whitespace-nowrap">{formatDate(row.date)}</Td>
                                        <Td>
                                            {row.subject ?? 'Daily'}
                                            <span className="block text-xs text-muted">{row.class}</span>
                                        </Td>
                                        <Td>
                                            <StatusBadge status={row.status} />
                                        </Td>
                                        <Td className="text-muted">{row.remarks}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                    <Pagination paginator={history} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
