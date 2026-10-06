import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import FilterBar from '@/Components/FilterBar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import StatusBadge from '@/Components/StatusBadge';
import Table, { Td, Th } from '@/Components/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option, Paginator } from '@/Types';
import { SchoolClassSummary } from '@/Types/academics';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    classes,
    filters,
    years,
    statuses,
    can,
}: {
    classes: Paginator<SchoolClassSummary>;
    filters: Record<string, string>;
    years: Option<number>[];
    statuses: Option[];
    can: { create: boolean };
}) {
    const addButton = can.create && <Button href={route('classes.create')}>New class</Button>;

    return (
        <AuthenticatedLayout>
            <Head title="Classes" />

            <PageHeader title="Classes" intro="Classes and sections for each academic year." actions={addButton} />

            <Card flush>
                <FilterBar url={route('classes.index')} filters={filters} searchLabel="Search by name, code or section">
                    {(values, set) => (
                        <>
                            <div>
                                <label htmlFor="filter-year" className="sr-only">
                                    Academic year
                                </label>
                                <SelectInput
                                    id="filter-year"
                                    value={values.academic_year_id ?? ''}
                                    options={years}
                                    placeholder="All years"
                                    onChange={(e) => set('academic_year_id', e.target.value)}
                                    className="w-full md:w-48"
                                />
                            </div>
                            <div>
                                <label htmlFor="filter-status" className="sr-only">
                                    Status
                                </label>
                                <SelectInput
                                    id="filter-status"
                                    value={values.status ?? ''}
                                    options={statuses}
                                    placeholder="All statuses"
                                    onChange={(e) => set('status', e.target.value)}
                                    className="w-full md:w-40"
                                />
                            </div>
                        </>
                    )}
                </FilterBar>

                {classes.data.length === 0 ? (
                    <EmptyState
                        icon="class"
                        title="No classes found"
                        message="Create a class, or choose another academic year in the filter."
                        action={addButton}
                    />
                ) : (
                    <Table caption="Classes">
                        <thead>
                            <tr>
                                <Th>Class</Th>
                                <Th>Code</Th>
                                <Th>Grade level</Th>
                                <Th>Academic year</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {classes.data.map((schoolClass) => (
                                <tr key={schoolClass.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <Link
                                            href={route('classes.show', schoolClass.id)}
                                            className="font-semibold hover:text-brand hover:underline"
                                        >
                                            {schoolClass.name}
                                        </Link>
                                    </Td>
                                    <Td className="font-mono text-xs">{schoolClass.code}</Td>
                                    <Td>{schoolClass.grade_level}</Td>
                                    <Td className="text-muted">{schoolClass.academic_year.name}</Td>
                                    <Td>
                                        <StatusBadge status={schoolClass.status} />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}

                <Pagination paginator={classes} />
            </Card>
        </AuthenticatedLayout>
    );
}
