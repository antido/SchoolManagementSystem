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
import { Subject } from '@/Types/academics';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    subjects,
    filters,
    statuses,
    can,
}: {
    subjects: Paginator<Subject>;
    filters: Record<string, string>;
    statuses: Option[];
    can: { create: boolean };
}) {
    const addButton = can.create && <Button href={route('subjects.create')}>Add subject</Button>;

    return (
        <AuthenticatedLayout>
            <Head title="Subjects" />

            <PageHeader
                title="Subjects"
                intro="The school's subject catalogue. Subjects are added to classes on each class's page."
                actions={addButton}
            />

            <Card flush>
                <FilterBar url={route('subjects.index')} filters={filters} searchLabel="Search by code or name">
                    {(values, set) => (
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
                    )}
                </FilterBar>

                {subjects.data.length === 0 ? (
                    <EmptyState icon="book" title="No subjects found" message="Add the subjects your school teaches." action={addButton} />
                ) : (
                    <Table caption="Subjects">
                        <thead>
                            <tr>
                                <Th>Code</Th>
                                <Th>Name</Th>
                                <Th>Taught to</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {subjects.data.map((subject) => (
                                <tr key={subject.id} className="hover:bg-canvas/60">
                                    <Td className="font-mono text-xs">{subject.code}</Td>
                                    <Td>
                                        <Link
                                            href={route('subjects.show', subject.id)}
                                            className="font-semibold hover:text-brand hover:underline"
                                        >
                                            {subject.name}
                                        </Link>
                                    </Td>
                                    <Td className="text-muted">
                                        {subject.classes_count} {subject.classes_count === 1 ? 'class' : 'classes'}
                                    </Td>
                                    <Td>
                                        <StatusBadge status={subject.status} />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}

                <Pagination paginator={subjects} />
            </Card>
        </AuthenticatedLayout>
    );
}
