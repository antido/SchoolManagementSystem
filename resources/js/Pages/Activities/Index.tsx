import ActivityList from '@/Components/Activities/ActivityList';
import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import FilterBar from '@/Components/FilterBar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option, Paginator } from '@/Types';
import { ActivitySummary } from '@/Types/activities';
import { Head } from '@inertiajs/react';

export default function Index({
    activities,
    filters,
    types,
    statuses,
    can,
}: {
    activities: Paginator<ActivitySummary>;
    filters: Record<string, string>;
    types: Option[];
    statuses: Option[];
    can: { create: boolean };
}) {
    const addButton = can.create && <Button href={route('activities.create')}>New activity</Button>;

    return (
        <AuthenticatedLayout>
            <Head title="Activities" />

            <PageHeader
                title="Activities"
                intro="Assignments, quizzes, examinations and other work for your classes."
                actions={addButton}
            />

            <Card flush>
                <FilterBar url={route('activities.index')} filters={filters} searchLabel="Search by title">
                    {(values, set) => (
                        <>
                            <div>
                                <label htmlFor="filter-type" className="sr-only">
                                    Kind
                                </label>
                                <SelectInput
                                    id="filter-type"
                                    value={values.type ?? ''}
                                    options={types}
                                    placeholder="All kinds"
                                    onChange={(e) => set('type', e.target.value)}
                                    className="w-full md:w-44"
                                />
                            </div>
                            {statuses.length > 0 && (
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
                        </>
                    )}
                </FilterBar>

                {activities.data.length === 0 ? (
                    <EmptyState
                        icon="clipboard"
                        title="No activities found"
                        message={
                            can.create
                                ? 'Create an assignment, quiz or examination for one of your classes.'
                                : 'Activities your teachers publish will appear here.'
                        }
                        action={addButton}
                    />
                ) : (
                    <ActivityList activities={activities.data} />
                )}

                <Pagination paginator={activities} />
            </Card>
        </AuthenticatedLayout>
    );
}
