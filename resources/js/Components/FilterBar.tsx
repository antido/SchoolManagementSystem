import Button from '@/Components/Button';
import Icon from '@/Components/Icon';
import { inputClasses } from '@/Components/TextInput';
import { router } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

/**
 * A search box plus optional filter selects for a list page. Submitting
 * reloads the page with the values in the address, so filtered lists can
 * be bookmarked and paginated.
 *
 *     <FilterBar url={route('students.index')} filters={filters}>
 *         {(values, set) => <SelectInput value={values.status} onChange={(e) => set('status', e.target.value)} ... />}
 *     </FilterBar>
 */
export default function FilterBar({
    url,
    filters,
    searchLabel = 'Search',
    children,
}: {
    url: string;
    filters: Record<string, string>;
    searchLabel?: string;
    children?: (
        values: Record<string, string>,
        set: (key: string, value: string) => void,
    ) => ReactNode;
}) {
    const [values, setValues] = useState<Record<string, string>>(filters);

    const set = (key: string, value: string) => setValues({ ...values, [key]: value });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        // Leave empty filters out of the address.
        const query = Object.fromEntries(
            Object.entries(values).filter(([, value]) => value !== '' && value !== null),
        );

        router.get(url, query, { preserveState: true, replace: true });
    };

    const hasFilters = Object.values(filters).some((value) => value);

    return (
        <form
            role="search"
            onSubmit={submit}
            className="flex flex-col gap-3 border-b border-line p-4 md:flex-row md:flex-wrap md:items-end"
        >
            <div className="relative min-w-0 flex-1 md:min-w-64">
                <label htmlFor="filter-search" className="sr-only">
                    {searchLabel}
                </label>
                <Icon
                    name="search"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
                />
                <input
                    id="filter-search"
                    type="search"
                    value={values.search ?? ''}
                    onChange={(e) => set('search', e.target.value)}
                    placeholder={searchLabel}
                    className={`${inputClasses} block w-full pl-9`}
                />
            </div>

            {children?.(values, set)}

            <div className="flex gap-2">
                <Button type="submit" variant="secondary">
                    Apply
                </Button>
                {hasFilters && (
                    <Button href={url} variant="ghost">
                        Clear
                    </Button>
                )}
            </div>
        </form>
    );
}
