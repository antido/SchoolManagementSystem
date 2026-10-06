import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import { SubmissionRosterRow } from '@/Types/activities';
import { formatDateTime } from '@/Utils/format';
import { Link } from '@inertiajs/react';

/** Every student in the class and where their work stands, for the teacher. */
export default function SubmissionsCard({ roster, totalPoints }: { roster: SubmissionRosterRow[]; totalPoints: number }) {
    const count = (status: string) => roster.filter((row) => row.status === status).length;
    const handedIn = roster.filter((row) => row.submission_id !== null).length;

    const summary = `${handedIn} of ${roster.length} handed in, ${count('graded')} graded`;

    return (
        <Card title="Submissions" actions={<span className="text-sm text-muted">{summary}</span>} flush>
            {roster.length === 0 ? (
                <EmptyState icon="student" title="No students in this class" message="Students enrolled in the class appear here." />
            ) : (
                <Table caption="Submissions">
                    <thead>
                        <tr>
                            <Th>Student</Th>
                            <Th>Status</Th>
                            <Th>Handed in</Th>
                            <Th className="text-right">Score</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {roster.map((row) => (
                            <tr key={row.student.id} className="hover:bg-canvas/60">
                                <Td>
                                    {row.submission_id ? (
                                        <Link
                                            href={route('submissions.show', row.submission_id)}
                                            className="font-semibold hover:text-brand hover:underline"
                                        >
                                            {row.student.name}
                                        </Link>
                                    ) : (
                                        <span className="font-semibold">{row.student.name}</span>
                                    )}
                                    <span className="block font-mono text-xs text-muted">{row.student.student_number}</span>
                                </Td>
                                <Td>
                                    <StatusBadge status={row.status} />
                                </Td>
                                <Td className="text-muted">
                                    {formatDateTime(row.submitted_at)}
                                    {row.is_late && row.status !== 'late' && <span className="ml-2 text-xs font-medium text-warning">Late</span>}
                                </Td>
                                <Td className="text-right tabular-nums">
                                    {row.score !== null ? `${row.score} / ${totalPoints}` : <span className="text-muted">—</span>}
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}
        </Card>
    );
}
