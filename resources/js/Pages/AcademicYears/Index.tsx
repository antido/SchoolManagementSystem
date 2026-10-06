import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { AcademicYear } from '@/Types/academics';
import { formatDate } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    years,
    can,
}: {
    years: Paginator<AcademicYear>;
    can: { create: boolean };
}) {
    const addButton = can.create && <Button href={route('academic-years.create')}>New academic year</Button>;

    return (
        <AuthenticatedLayout>
            <Head title="Academic years" />

            <PageHeader
                title="Academic years"
                intro="School years and their terms. The active year and term are where new classes and records go."
                actions={addButton}
            />

            <Card flush>
                {years.data.length === 0 ? (
                    <EmptyState
                        icon="calendar"
                        title="No academic years yet"
                        message="Create the current school year, then add its terms or semesters."
                        action={addButton}
                    />
                ) : (
                    <Table caption="Academic years">
                        <thead>
                            <tr>
                                <Th>Name</Th>
                                <Th>Dates</Th>
                                <Th>Terms</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {years.data.map((year) => (
                                <tr key={year.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <Link
                                            href={route('academic-years.show', year.id)}
                                            className="font-semibold hover:text-brand hover:underline"
                                        >
                                            {year.name}
                                        </Link>
                                    </Td>
                                    <Td className="text-muted">
                                        {formatDate(year.start_date)} to {formatDate(year.end_date)}
                                    </Td>
                                    <Td>{year.terms_count}</Td>
                                    <Td>{year.is_active ? <Badge tone="success">Active</Badge> : <Badge>Inactive</Badge>}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
                <Pagination paginator={years} />
            </Card>
        </AuthenticatedLayout>
    );
}
