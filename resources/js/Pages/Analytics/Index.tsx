import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import { BarList, ChartFrame, ColumnChart, LineChart, ordinalColors } from '@/Components/Charts';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    stats: {
        students: number;
        classes: number;
        average_grade: number | null;
        attendance_rate: number | null;
        completion_rate: number | null;
        exam_average: number | null;
    };
    passing: number;
    gradeDistribution: { label: string; min: number; value: number }[];
    attendanceTrend: { week: string; rate: number | null; days: number }[];
    enrollmentByClass: { label: string; value: number }[];
    examBySubject: { label: string; value: number }[];
    topStudents: { id: number; name: string; average: number }[];
    needsAttention: { id: number; name: string; average: number | null; attendance: number | null; missing: number; reasons: string[] }[];
    filters: { academic_year_id: number | null; school_class_id: number | null };
    options: { academicYears: Option<number>[]; classes: Option<number>[] };
    thresholds: { attendance: number; missing: number };
}

const percent = (value: number | null) => (value === null ? '—' : `${value}%`);
const weekLabel = (date: string) => new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(`${date}T00:00:00`));

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="rounded-xl border border-line bg-surface px-5 py-4">
            <dt className="text-sm text-muted">{label}</dt>
            <dd className="mt-1 font-display text-2xl font-semibold">{value}</dd>
            {hint && <dd className="text-xs text-muted">{hint}</dd>}
        </div>
    );
}

/** Performance figures for the chosen year and class, with charts and the students to follow up. */
/** Students listed under "needing attention" before "Show all". */
const ATTENTION_PREVIEW = 8;

export default function Index(props: Props) {
    const { stats, filters, options } = props;
    const [showAllAttention, setShowAllAttention] = useState(false);

    const filter = (key: 'academic_year_id' | 'school_class_id', value: string) => {
        const next = { ...filters, [key]: value || null, ...(key === 'academic_year_id' ? { school_class_id: null } : {}) };
        router.get(route('analytics'), Object.fromEntries(Object.entries(next).filter(([, v]) => v)), { preserveScroll: true });
    };

    const hasGrades = props.gradeDistribution.some((band) => band.value > 0);
    const hasAttendance = props.attendanceTrend.some((week) => week.rate !== null);

    return (
        <AuthenticatedLayout>
            <Head title="Analytics" />
            <PageHeader title="Analytics" intro="How students are doing, from current grades, attendance and handed-in work. Figures refresh every five minutes." />

            <div className="mb-6 flex flex-col gap-3 sm:flex-row">
                <div>
                    <label htmlFor="analytics-year" className="sr-only">
                        Academic year
                    </label>
                    <SelectInput
                        id="analytics-year"
                        value={filters.academic_year_id ?? ''}
                        options={options.academicYears}
                        placeholder="All years"
                        onChange={(e) => filter('academic_year_id', e.target.value)}
                        className="w-full sm:w-48"
                    />
                </div>
                <div>
                    <label htmlFor="analytics-class" className="sr-only">
                        Class
                    </label>
                    <SelectInput
                        id="analytics-class"
                        value={filters.school_class_id ?? ''}
                        options={options.classes}
                        placeholder="All classes"
                        onChange={(e) => filter('school_class_id', e.target.value)}
                        className="w-full sm:w-64"
                    />
                </div>
            </div>

            <dl className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Stat label="Students enrolled" value={stats.students.toLocaleString()} hint={`${stats.classes} ${stats.classes === 1 ? 'class' : 'classes'}`} />
                <Stat label="Average grade" value={percent(stats.average_grade)} hint={`${props.passing}% passes`} />
                <Stat label="Attendance" value={percent(stats.attendance_rate)} hint="Excused days left out" />
                <Stat label="Work handed in" value={percent(stats.completion_rate)} hint="Of assignments already due" />
                <Stat label="Exam average" value={percent(stats.exam_average)} hint="Graded quiz and exam attempts" />
            </dl>

            <div className="grid gap-6 lg:grid-cols-2">
                <ChartFrame
                    title="Grade distribution"
                    description="Students' current grades by descriptor, lowest to highest."
                    table={{ columns: ['Descriptor', 'From', 'Grades'], rows: props.gradeDistribution.map((band) => [band.label, `${band.min}%`, band.value]) }}
                >
                    {hasGrades ? (
                        <ColumnChart data={props.gradeDistribution} colors={ordinalColors(props.gradeDistribution.length)} />
                    ) : (
                        <EmptyState icon="chart" title="No grades computed yet" />
                    )}
                </ChartFrame>

                <ChartFrame
                    title="Attendance by week"
                    description="Share of recorded days attended, last 12 weeks."
                    table={{ columns: ['Week of', 'Attendance', 'Days recorded'], rows: props.attendanceTrend.map((week) => [weekLabel(week.week), percent(week.rate), week.days]) }}
                >
                    {hasAttendance ? (
                        <LineChart
                            points={props.attendanceTrend.map((week) => ({ label: weekLabel(week.week), value: week.rate }))}
                            reference={{ value: props.thresholds.attendance, label: `${props.thresholds.attendance}%` }}
                        />
                    ) : (
                        <EmptyState icon="check" title="No attendance in the last 12 weeks" />
                    )}
                </ChartFrame>

                <ChartFrame
                    title="Enrollment by class"
                    table={{ columns: ['Class', 'Students'], rows: props.enrollmentByClass.map((row) => [row.label, row.value]) }}
                >
                    {props.enrollmentByClass.length === 0 ? <EmptyState icon="class" title="No classes" /> : <BarList data={props.enrollmentByClass.slice(0, 12)} />}
                </ChartFrame>

                <ChartFrame
                    title="Exam average by subject"
                    table={{ columns: ['Subject', 'Average'], rows: props.examBySubject.map((row) => [row.label, `${row.value}%`]) }}
                >
                    {props.examBySubject.length === 0 ? (
                        <EmptyState icon="exam" title="No graded quizzes or exams yet" />
                    ) : (
                        <BarList data={props.examBySubject} max={100} format={(value) => `${value}%`} />
                    )}
                </ChartFrame>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card title="Top-performing students" flush>
                    {props.topStudents.length === 0 ? (
                        <EmptyState icon="award" title="No grades computed yet" />
                    ) : (
                        <ol className="divide-y divide-line">
                            {props.topStudents.map((student, index) => (
                                <li key={student.id} className="flex items-center gap-3 px-5 py-2.5">
                                    <span className="w-5 text-right text-sm text-muted tabular-nums">{index + 1}</span>
                                    <Link href={route('students.show', student.id)} className="flex-1 font-medium hover:text-brand hover:underline">
                                        {student.name}
                                    </Link>
                                    <span className="font-semibold tabular-nums">{student.average}%</span>
                                </li>
                            ))}
                        </ol>
                    )}
                </Card>

                <Card
                    title="Students needing attention"
                    actions={<span className="text-xs text-muted">Failing average, attendance under {props.thresholds.attendance}%, or {props.thresholds.missing}+ missing assignments</span>}
                    flush
                >
                    {props.needsAttention.length === 0 ? (
                        <EmptyState icon="check" title="No one to follow up right now" />
                    ) : (
                        <ul className="divide-y divide-line">
                            {(showAllAttention ? props.needsAttention : props.needsAttention.slice(0, ATTENTION_PREVIEW)).map((student) => (
                                <li key={student.id} className="px-5 py-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={route('students.show', student.id)} className="font-medium hover:text-brand hover:underline">
                                            {student.name}
                                        </Link>
                                        {student.reasons.map((reason) => (
                                            <Badge key={reason} tone={reason === 'Failing average' ? 'danger' : 'warning'}>
                                                {reason}
                                            </Badge>
                                        ))}
                                    </div>
                                    <p className="mt-1 text-xs text-muted tabular-nums">
                                        Average {percent(student.average)}, attendance {percent(student.attendance)}, {student.missing} missing
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                    {props.needsAttention.length > ATTENTION_PREVIEW && (
                        <button
                            type="button"
                            onClick={() => setShowAllAttention(!showAllAttention)}
                            aria-expanded={showAllAttention}
                            className="block w-full border-t border-line px-5 py-3 text-center text-sm font-medium text-brand hover:bg-canvas"
                        >
                            {showAllAttention ? 'Show fewer' : `Show all ${props.needsAttention.length}`}
                        </button>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
