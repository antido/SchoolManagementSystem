import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import { AttemptRosterRow } from '@/Types/activities';
import { Link } from '@inertiajs/react';

/** Every student in the class with their attempts and best score, for the teacher. */
export default function AttemptsCard({ roster, totalPoints }: { roster: AttemptRosterRow[]; totalPoints: number }) {
    const started = roster.filter((row) => row.attempts > 0).length;
    const toScore = roster.filter((row) => row.status === 'submitted').length;

    return (
        <Card
            title="Results"
            actions={
                <span className="text-sm text-muted">
                    {started} of {roster.length} attempted{toScore > 0 && `, ${toScore} to score`}
                </span>
            }
            flush
        >
            {roster.length === 0 ? (
                <EmptyState icon="student" title="No students in this class" message="Students enrolled in the class appear here." />
            ) : (
                <Table caption="Results">
                    <thead>
                        <tr>
                            <Th>Student</Th>
                            <Th>Latest</Th>
                            <Th>Attempts</Th>
                            <Th className="text-right">Best score</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {roster.map((row) => (
                            <tr key={row.student.id} className="hover:bg-canvas/60">
                                <Td>
                                    {row.latest_attempt_id ? (
                                        <Link
                                            href={route('attempts.show', row.latest_attempt_id)}
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
                                <Td className="tabular-nums text-muted">{row.attempts}</Td>
                                <Td className="text-right tabular-nums">
                                    {row.best_score !== null ? `${row.best_score} / ${totalPoints}` : <span className="text-muted">—</span>}
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}
        </Card>
    );
}
