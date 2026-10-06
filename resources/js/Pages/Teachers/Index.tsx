import Avatar from '@/Components/Avatar';
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
import { TeacherSummary } from '@/Types/people';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    teachers,
    filters,
    statuses,
    can,
}: {
    teachers: Paginator<TeacherSummary>;
    filters: Record<string, string>;
    statuses: Option[];
    can: { create: boolean };
}) {
    const filtered = Boolean(filters.search || filters.status);

    return (
        <AuthenticatedLayout>
            <Head title="Teachers" />

            <PageHeader
                title="Teachers"
                intro="Teacher profiles and the accounts they sign in with."
                actions={can.create && <Button href={route('teachers.create')}>Add teacher</Button>}
            />

            <Card flush>
                <FilterBar
                    url={route('teachers.index')}
                    filters={filters}
                    searchLabel="Search by name, employee number, subject or email"
                >
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
                                className="w-full md:w-44"
                            />
                        </div>
                    )}
                </FilterBar>

                {teachers.data.length === 0 ? (
                    <EmptyState
                        icon="teacher"
                        title={filtered ? 'No teachers match' : 'No teachers yet'}
                        message={filtered ? 'Try a different search or clear the filters.' : 'Add the first teacher to get started.'}
                        action={can.create && !filtered && <Button href={route('teachers.create')}>Add teacher</Button>}
                    />
                ) : (
                    <Table caption="Teachers">
                        <thead>
                            <tr>
                                <Th>Name</Th>
                                <Th>Employee number</Th>
                                <Th>Specialization</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {teachers.data.map((teacher) => (
                                <tr key={teacher.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <div className="flex items-center gap-3">
                                            <Avatar name={teacher.full_name} size="sm" />
                                            <div className="min-w-0">
                                                <Link
                                                    href={route('teachers.show', teacher.id)}
                                                    className="font-semibold hover:text-brand hover:underline"
                                                >
                                                    {teacher.sortable_name}
                                                </Link>
                                                <p className="truncate text-muted">{teacher.email}</p>
                                            </div>
                                        </div>
                                    </Td>
                                    <Td className="font-mono text-xs">{teacher.employee_number}</Td>
                                    <Td>{teacher.specialization ?? <span className="text-muted">—</span>}</Td>
                                    <Td>
                                        <StatusBadge status={teacher.status} />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}

                <Pagination paginator={teachers} />
            </Card>
        </AuthenticatedLayout>
    );
}
