import Card from '@/Components/Card';
import Icon from '@/Components/Icon';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface ReportInfo {
    key: string;
    title: string;
    description: string;
}

/** The reports this person may run, by group. */
export default function Index({ groups }: { groups: { name: string; reports: ReportInfo[] }[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Reports" />
            <PageHeader title="Reports" intro="Choose a report, set its filters, then view it here or download it as PDF, Excel or CSV." />

            <div className="space-y-6">
                {groups.map((group) => (
                    <Card key={group.name} title={group.name} flush>
                        <ul className="divide-y divide-line">
                            {group.reports.map((report) => (
                                <li key={report.key}>
                                    <Link href={route('reports.show', report.key)} className="flex items-center gap-3 px-5 py-4 hover:bg-canvas/60">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
                                            <Icon name="report" className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-semibold">{report.title}</span>
                                            <span className="block text-sm text-muted">{report.description}</span>
                                        </span>
                                        <Icon name="chevronRight" className="size-4 text-muted" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
