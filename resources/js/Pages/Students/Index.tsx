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
import { StudentSummary } from '@/Types/people';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    students,
    filters,
    statuses,
    can,
}: {
    students: Paginator<StudentSummary>;
    filters: Record<string, string>;
    statuses: Option[];
    can: { create: boolean };
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Students" />

            <PageHeader
                title="Students"
                intro="Student profiles and the accounts they sign in with."
                actions={can.create && <Button href={route('students.create')}>Add student</Button>}
            />

            <Card flush>
                <FilterBar
                    url={route('students.index')}
                    filters={filters}
                    searchLabel="Search by name, student number or email"
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

                {students.data.length === 0 ? (
                    <EmptyState
                        icon="student"
                        title={filters.search || filters.status ? 'No students match' : 'No students yet'}
                        message={
                            filters.search || filters.status
                                ? 'Try a different search or clear the filters.'
                                : 'Add the first student to get started.'
                        }
                        action={
                            can.create && !filters.search && <Button href={route('students.create')}>Add student</Button>
                        }
                    />
                ) : (
                    <Table caption="Students">
                        <thead>
                            <tr>
                                <Th>Name</Th>
                                <Th>Student number</Th>
                                <Th>Email</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.map((student) => (
                                <tr key={student.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <div className="flex items-center gap-3">
                                            <Avatar name={student.full_name} size="sm" />
                                            <Link
                                                href={route('students.show', student.id)}
                                                className="font-semibold hover:text-brand hover:underline"
                                            >
                                                {student.sortable_name}
                                            </Link>
                                        </div>
                                    </Td>
                                    <Td className="font-mono text-xs">{student.student_number}</Td>
                                    <Td className="text-muted">{student.email}</Td>
                                    <Td>
                                        <StatusBadge status={student.status} />
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}

                <Pagination paginator={students} />
            </Card>
        </AuthenticatedLayout>
    );
}
