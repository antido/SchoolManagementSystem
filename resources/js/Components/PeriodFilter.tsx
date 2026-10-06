import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import { router } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

/**
 * From and to dates (plus any extra filters) that reload the page with
 * the period in the address.
 */
export default function PeriodFilter({
    url,
    filters,
    children,
}: {
    url: string;
    filters: Record<string, string | number | null>;
    children?: (values: Record<string, string>, set: (key: string, value: string) => void) => ReactNode;
}) {
    const [values, setValues] = useState<Record<string, string>>(
        Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, value === null ? '' : String(value)])),
    );

    const set = (key: string, value: string) => setValues({ ...values, [key]: value });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(url, Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '')), { preserveState: true, replace: true });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div>
                <label htmlFor="period-from" className="text-sm font-medium">
                    From
                </label>
                <TextInput id="period-from" type="date" value={values.from} onChange={(e) => set('from', e.target.value)} className="mt-1 block" />
            </div>
            <div>
                <label htmlFor="period-to" className="text-sm font-medium">
                    To
                </label>
                <TextInput id="period-to" type="date" value={values.to} onChange={(e) => set('to', e.target.value)} className="mt-1 block" />
            </div>
            {children?.(values, set)}
            <Button type="submit" variant="secondary">
                Apply
            </Button>
        </form>
    );
}
