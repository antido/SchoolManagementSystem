import ActivityList from '@/Components/Activities/ActivityList';
import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import { IconName } from '@/Components/Icon';
import PageHeader from '@/Components/PageHeader';
import StatCard from '@/Components/StatCard';
import SummaryList from '@/Components/SummaryList';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/Types';
import { ActivitySummary } from '@/Types/activities';
import { Head, Link, usePage } from '@inertiajs/react';
import ClassList, { DashboardClass } from './Dashboard/Partials/ClassList';
import RecentActivity, { ActivityItem } from './Dashboard/Partials/RecentActivity';

interface Stat {
    label: string;
    value: number;
    icon: IconName;
    href?: string | null;
}

interface DashboardProps {
    type: 'admin' | 'teacher' | 'student' | 'hr' | 'basic';
    period: { year: string | null; term: string | null };
    stats?: Stat[];
    academic?: { label: string; value: number }[];
    assignments?: { label: string; value: number }[];
    recent?: ActivityItem[];
    classes?: DashboardClass[];
    unassignedTeachers?: { id: number; name: string; specialization: string | null }[];
    teacherId?: number;
    studentId?: number;
    upcoming?: ActivitySummary[];
}

function Upcoming({ activities }: { activities: ActivitySummary[] }) {
    return (
        <Card title="Coming up" actions={<Button small variant="ghost" href={route('activities.index')}>All activities</Button>} flush>
            {activities.length === 0 ? (
                <EmptyState icon="clipboard" title="Nothing due" message="Published activities that are still open appear here." />
            ) : (
                <ActivityList activities={activities} />
            )}
        </Card>
    );
}

function Stats({ stats }: { stats: Stat[] }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {stats.map((stat) => (
                <StatCard key={stat.label} {...stat} />
            ))}
        </div>
    );
}

export default function Dashboard(props: DashboardProps) {
    const { auth } = usePage<PageProps>().props;
    const firstName = auth.user!.name.split(' ')[0];

    const period = props.period.year ? (
        <span className="inline-flex flex-wrap items-center gap-2">
            <Badge tone="brand">{props.period.year}</Badge>
            {props.period.term && <Badge>{props.period.term}</Badge>}
        </span>
    ) : (
        'No academic year is active yet.'
    );

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <PageHeader title={`Welcome back, ${firstName}`} intro={period} />

            <div className="space-y-6">
                {props.type === 'admin' && (
                    <>
                        <Stats stats={props.stats!} />
                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="lg:col-span-2">
                                <RecentActivity items={props.recent!} />
                            </div>
                            <SummaryList title="This academic year" items={props.academic!} />
                        </div>
                    </>
                )}

                {props.type === 'teacher' && (
                    <>
                        <Stats stats={props.stats!} />
                        <div className="grid gap-6 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <Upcoming activities={props.upcoming!} />
                                <ClassList classes={props.classes!} emptyMessage="Classes you advise or teach in the active year appear here." />
                            </div>
                            <Card title="My profile">
                                <p className="text-sm text-muted">Your details, classes and subjects.</p>
                                <Button variant="secondary" className="mt-4" href={route('teachers.show', props.teacherId!)}>
                                    View my profile
                                </Button>
                            </Card>
                        </div>
                    </>
                )}

                {props.type === 'student' && (
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            <Upcoming activities={props.upcoming!} />
                            <ClassList classes={props.classes!} emptyMessage="You will see your class here once you are enrolled." />
                        </div>
                        <Card title="My profile">
                            <p className="text-sm text-muted">Your details and class history.</p>
                            <Button variant="secondary" className="mt-4" href={route('students.show', props.studentId!)}>
                                View my profile
                            </Button>
                        </Card>
                    </div>
                )}

                {props.type === 'hr' && (
                    <>
                        <Stats stats={props.stats!} />
                        <div className="grid gap-6 lg:grid-cols-2">
                            <SummaryList title="Teacher assignments" items={props.assignments!} />
                            <Card title="Teachers without a class this year" flush>
                                {props.unassignedTeachers!.length === 0 ? (
                                    <EmptyState icon="teacher" title="Every teacher has a class" />
                                ) : (
                                    <ul className="divide-y divide-line">
                                        {props.unassignedTeachers!.map((teacher) => (
                                            <li key={teacher.id} className="px-5 py-3">
                                                <Link href={route('teachers.show', teacher.id)} className="font-medium hover:text-brand hover:underline">
                                                    {teacher.name}
                                                </Link>
                                                {teacher.specialization && <p className="text-xs text-muted">{teacher.specialization}</p>}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Card>
                        </div>
                    </>
                )}

                {props.type === 'basic' && (
                    <Card>
                        <EmptyState
                            icon="home"
                            title="Your account has no role yet"
                            message="Ask the school administrator to give your account a role."
                        />
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
