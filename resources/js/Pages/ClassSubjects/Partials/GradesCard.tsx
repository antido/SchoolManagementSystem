import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import SelectInput from '@/Components/SelectInput';
import Table, { Td, Th } from '@/Components/Table';
import { useRequest } from '@/Hooks/useRequest';
import { Option } from '@/Types';
import { GradingSystem, StudentGrade } from '@/Types/grades';
import { formatDateTime } from '@/Utils/format';
import { Link } from '@inertiajs/react';

export interface GradesCardData {
    system: GradingSystem | null;
    passing: number | null;
    systems: Option<number>[];
    chosen_system_id: number | null;
    rows: { student: { id: number; name: string; student_number: string }; grade: StudentGrade | null }[];
    can_manage: boolean;
}

/** The class subject's grades, with the grading system and the compute button. */
export default function GradesCard({ classSubjectId, grades }: { classSubjectId: number; grades: GradesCardData }) {
    const { busy, send } = useRequest();

    const weights = grades.system?.components.map((component) => `${component.name} ${component.weight}%`).join(', ');
    const lastComputed = grades.rows
        .map((row) => row.grade?.computed_at)
        .filter((value): value is string => !!value)
        .sort()
        .at(-1);

    return (
        <Card
            title="Grades"
            actions={
                grades.can_manage && (
                    <Button small disabled={busy || !grades.system} onClick={() => send('post', route('grades.compute', classSubjectId))}>
                        {busy ? 'Computing…' : 'Compute grades'}
                    </Button>
                )
            }
            flush
        >
            <div className="space-y-3 border-b border-line px-5 py-4 text-sm">
                {grades.system ? (
                    <p className="text-muted">
                        <span className="font-medium text-ink">{grades.system.name}</span>: {weights}. Passing: {grades.passing}%.
                    </p>
                ) : (
                    <p className="text-danger">No grading system is set up. Ask an administrator to add one.</p>
                )}
                {lastComputed && <p className="text-xs text-muted">Last computed {formatDateTime(lastComputed)}.</p>}
                {grades.can_manage && grades.systems.length > 1 && (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <label htmlFor="grading-system" className="font-medium">
                            Grading system
                        </label>
                        <SelectInput
                            id="grading-system"
                            value={grades.chosen_system_id ?? ''}
                            options={grades.systems}
                            placeholder="School default"
                            disabled={busy}
                            onChange={(e) => send('patch', route('grades.system', classSubjectId), { grading_system_id: e.target.value || null })}
                            className="sm:w-72"
                        />
                    </div>
                )}
            </div>

            {grades.rows.length === 0 ? (
                <EmptyState icon="student" title="No students in this class" />
            ) : (
                <Table caption="Grades">
                    <thead>
                        <tr>
                            <Th>Student</Th>
                            <Th className="text-right">Grade</Th>
                            <Th>Descriptor</Th>
                            <Th>Remarks</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {grades.rows.map(({ student, grade }) => (
                            <tr key={student.id} className="hover:bg-canvas/60">
                                <Td>
                                    <Link
                                        href={route('grades.student', [classSubjectId, student.id])}
                                        className="font-semibold hover:text-brand hover:underline"
                                    >
                                        {student.name}
                                    </Link>
                                    <span className="block font-mono text-xs text-muted">{student.student_number}</span>
                                </Td>
                                <Td className="text-right font-display font-semibold tabular-nums">
                                    {grade ? `${grade.percentage}%` : <span className="font-sans font-normal text-muted">Not computed</span>}
                                </Td>
                                <Td className="text-muted">{grade?.descriptor}</Td>
                                <Td>{grade && <Badge tone={grade.passed ? 'success' : 'danger'}>{grade.passed ? 'Passed' : 'Failed'}</Badge>}</Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}
        </Card>
    );
}
