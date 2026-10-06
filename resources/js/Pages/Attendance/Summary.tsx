import AttendanceStats, { AttendanceSummary } from '@/Components/AttendanceStats';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PeriodFilter from '@/Components/PeriodFilter';
import SelectInput from '@/Components/SelectInput';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { SchoolClassSummary } from '@/Types/academics';
import { cn, formatDate } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

/** Each student's attendance in a class over a period. */
export default function Summary({
    schoolClass,
    filters,
    subjects,
    days,
    overall,
    rows,
}: {
    schoolClass: SchoolClassSummary;
    filters: { from: string; to: string; class_subject: number | null };
    subjects: Option<number>[];
    days: number;
    overall: AttendanceSummary;
    rows: { student: { id: number; name: string; student_number: string }; summary: AttendanceSummary }[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title={`Attendance summary, ${schoolClass.name}`} />

            <PageHeader
                title={`Attendance summary: ${schoolClass.name}`}
                intro={`${formatDate(filters.from)} to ${formatDate(filters.to)}, ${days} ${days === 1 ? 'day' : 'days'} recorded.`}
                breadcrumbs={[
                    { label: 'Attendance', href: route('attendance.index') },
                    { label: schoolClass.name, href: route('classes.show', schoolClass.id) },
                    { label: 'Summary' },
                ]}
                actions={
                    <Button variant="secondary" href={route('attendance.sheet', [schoolClass.id, { class_subject: filters.class_subject ?? undefined }])}>
                        Open sheet
                    </Button>
                }
            />

            <div className="space-y-6">
                <Card>
                    <AttendanceStats summary={overall} />
                </Card>

                <Card flush>
                    <PeriodFilter url={route('attendance.summary', schoolClass.id)} filters={filters}>
                        {(values, set) => (
                            <div className="min-w-0 sm:w-72">
                                <label htmlFor="summary-subject" className="text-sm font-medium">
                                    For
                                </label>
                                <SelectInput
                                    id="summary-subject"
                                    value={values.class_subject ?? ''}
                                    options={subjects}
                                    placeholder="Daily attendance"
                                    onChange={(e) => set('class_subject', e.target.value)}
                                    className="mt-1 w-full"
                                />
                            </div>
                        )}
                    </PeriodFilter>

                    {rows.length === 0 ? (
                        <EmptyState icon="student" title="No students" />
                    ) : (
                        <Table caption="Attendance by student">
                            <thead>
                                <tr>
                                    <Th>Student</Th>
                                    <Th className="text-right">Present</Th>
                                    <Th className="text-right">Late</Th>
                                    <Th className="text-right">Absent</Th>
                                    <Th className="text-right">Excused</Th>
                                    <Th className="text-right">Attendance</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map(({ student, summary }) => (
                                    <tr key={student.id} className="hover:bg-canvas/60">
                                        <Td>
                                            <Link
                                                href={route('attendance.student', [student.id, { from: filters.from, to: filters.to }])}
                                                className="font-semibold hover:text-brand hover:underline"
                                            >
                                                {student.name}
                                            </Link>
                                            <span className="block font-mono text-xs text-muted">{student.student_number}</span>
                                        </Td>
                                        <Td className="text-right tabular-nums">{summary.present}</Td>
                                        <Td className="text-right tabular-nums">{summary.late}</Td>
                                        <Td className="text-right tabular-nums">{summary.absent}</Td>
                                        <Td className="text-right tabular-nums">{summary.excused}</Td>
                                        <Td
                                            className={cn(
                                                'text-right font-semibold tabular-nums',
                                                summary.percentage !== null && summary.percentage < 80 && 'text-danger',
                                            )}
                                        >
                                            {summary.percentage === null ? <span className="font-normal text-muted">—</span> : `${summary.percentage}%`}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
