import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import FilterBar from '@/Components/FilterBar';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option, Paginator } from '@/Types';
import { formatDateTime, humanize } from '@/Utils/format';
import { Head } from '@inertiajs/react';

interface Entry {
    id: number;
    action: string;
    description: string;
    actor: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
}

const show = (value: unknown) => (value === null || value === undefined || value === '' ? '—' : typeof value === 'object' ? JSON.stringify(value) : String(value));

/** What changed: one row per field, before and after. */
function Changes({ entry }: { entry: Entry }) {
    const fields = [...new Set([...Object.keys(entry.old_values ?? {}), ...Object.keys(entry.new_values ?? {})])];

    if (fields.length === 0) {
        return null;
    }

    return (
        <details className="mt-2">
            <summary className="cursor-pointer text-sm font-medium text-brand">
                {fields.length} {fields.length === 1 ? 'field' : 'fields'}
            </summary>
            <div className="mt-2 overflow-x-auto">
                <table className="w-full text-left text-xs">
                    <thead className="text-muted">
                        <tr>
                            <th className="py-1 pr-4 font-medium">Field</th>
                            {entry.old_values && <th className="py-1 pr-4 font-medium">Before</th>}
                            {entry.new_values && <th className="py-1 font-medium">After</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {fields.map((field) => (
                            <tr key={field} className="border-t border-line align-top">
                                <td className="py-1 pr-4 font-medium whitespace-nowrap">{humanize(field)}</td>
                                {entry.old_values && <td className="py-1 pr-4 break-all text-muted">{show(entry.old_values[field])}</td>}
                                {entry.new_values && <td className="py-1 break-all">{show(entry.new_values[field])}</td>}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}

/** The audit trail: who changed what, and when. Read-only. */
export default function Index({ entries, filters, areas }: { entries: Paginator<Entry>; filters: Record<string, string>; areas: Option[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Audit trail" />
            <PageHeader title="Audit trail" intro="Important changes to accounts, people, classes, work, grades, attendance, awards and the forum. Entries cannot be edited or deleted." />

            <Card flush>
                <FilterBar url={route('audit.index')} filters={filters} searchLabel="Search descriptions or people">
                    {(values, set) => (
                        <>
                            <div>
                                <label htmlFor="audit-area" className="sr-only">
                                    Kind of record
                                </label>
                                <SelectInput
                                    id="audit-area"
                                    value={values.area ?? ''}
                                    options={areas}
                                    placeholder="All records"
                                    onChange={(e) => set('area', e.target.value)}
                                    className="w-full md:w-44"
                                />
                            </div>
                            <div>
                                <label htmlFor="audit-from" className="sr-only">
                                    From
                                </label>
                                <TextInput id="audit-from" type="date" value={values.from ?? ''} onChange={(e) => set('from', e.target.value)} />
                            </div>
                            <div>
                                <label htmlFor="audit-to" className="sr-only">
                                    To
                                </label>
                                <TextInput id="audit-to" type="date" value={values.to ?? ''} onChange={(e) => set('to', e.target.value)} />
                            </div>
                        </>
                    )}
                </FilterBar>

                {entries.data.length === 0 ? (
                    <EmptyState icon="log" title="No entries" message="Changes appear here as people use the system." />
                ) : (
                    <ol className="divide-y divide-line">
                        {entries.data.map((entry) => (
                            <li key={entry.id} className="px-5 py-3">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <p className="font-medium">{entry.description}</p>
                                    <time dateTime={entry.created_at} className="text-xs text-muted tabular-nums">
                                        {formatDateTime(entry.created_at)}
                                    </time>
                                </div>
                                <p className="text-xs text-muted">
                                    {entry.actor ? `By ${entry.actor}` : 'By the system'}
                                    {entry.ip_address && `, from ${entry.ip_address}`}
                                    <span className="ml-2 font-mono">{entry.action}</span>
                                </p>
                                <Changes entry={entry} />
                            </li>
                        ))}
                    </ol>
                )}
                <Pagination paginator={entries} />
            </Card>
        </AuthenticatedLayout>
    );
}
