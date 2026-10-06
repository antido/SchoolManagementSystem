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
import { UserSummary } from '@/Types/users';
import { formatDateTime } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

export default function Index({
    users,
    filters,
    roles,
    can,
}: {
    users: Paginator<UserSummary>;
    filters: Record<string, string>;
    roles: Option[];
    can: { create: boolean };
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Users" />

            <PageHeader
                title="Users"
                intro="Every account that can sign in, with its role and status."
                actions={can.create && <Button href={route('users.create')}>New account</Button>}
            />

            <Card flush>
                <FilterBar url={route('users.index')} filters={filters} searchLabel="Search by name or email">
                    {(values, set) => (
                        <>
                            <div>
                                <label htmlFor="filter-role" className="sr-only">
                                    Role
                                </label>
                                <SelectInput
                                    id="filter-role"
                                    value={values.role ?? ''}
                                    options={roles}
                                    placeholder="All roles"
                                    onChange={(e) => set('role', e.target.value)}
                                    className="w-full md:w-44"
                                />
                            </div>
                            <div>
                                <label htmlFor="filter-status" className="sr-only">
                                    Status
                                </label>
                                <SelectInput
                                    id="filter-status"
                                    value={values.status ?? ''}
                                    placeholder="All statuses"
                                    options={[
                                        { value: 'active', label: 'Active' },
                                        { value: 'inactive', label: 'Inactive' },
                                    ]}
                                    onChange={(e) => set('status', e.target.value)}
                                    className="w-full md:w-40"
                                />
                            </div>
                        </>
                    )}
                </FilterBar>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon="users"
                        title="No accounts match"
                        message="Try a different search or clear the filters."
                    />
                ) : (
                    <Table caption="User accounts">
                        <thead>
                            <tr>
                                <Th>Name</Th>
                                <Th>Role</Th>
                                <Th>Status</Th>
                                <Th>Last sign-in</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-canvas/60">
                                    <Td>
                                        <div className="flex items-center gap-3">
                                            <Avatar name={user.name} size="sm" />
                                            <div className="min-w-0">
                                                <Link
                                                    href={route('users.show', user.id)}
                                                    className="font-semibold hover:text-brand hover:underline"
                                                >
                                                    {user.name}
                                                </Link>
                                                <p className="truncate text-muted">{user.email}</p>
                                            </div>
                                        </div>
                                    </Td>
                                    <Td>{user.role_label ?? <span className="text-muted">None</span>}</Td>
                                    <Td>
                                        <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                                    </Td>
                                    <Td className="text-muted">
                                        {user.last_login_at ? formatDateTime(user.last_login_at) : 'Never'}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}

                <Pagination paginator={users} />
            </Card>
        </AuthenticatedLayout>
    );
}
