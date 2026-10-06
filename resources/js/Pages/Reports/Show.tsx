import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import SelectInput from '@/Components/SelectInput';
import Table, { Td, Th } from '@/Components/Table';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Option } from '@/Types';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type FilterKey = 'academic_year_id' | 'academic_term_id' | 'school_class_id' | 'class_subject_id' | 'student_id' | 'from' | 'to';

interface ReportInfo {
    key: string;
    title: string;
    description: string;
    filters: FilterKey[];
    required: FilterKey[];
}

interface Result {
    title: string;
    subtitle: string[];
    columns: string[];
    rows: (string | number | null)[][];
    total_rows: number;
    summary: { label: string; value: string }[];
}

const formatLabels: Record<string, string> = { pdf: 'PDF', xlsx: 'Excel', csv: 'CSV' };

/** One report: its filters, an on-screen preview, and downloads. */
export default function Show({
    report,
    values,
    options,
    studentOptions,
    result,
    formats,
    can,
}: {
    report: ReportInfo;
    values: Partial<Record<FilterKey, string>>;
    options: {
        academicYears: Option<number>[];
        academicTerms: Option<number>[];
        classes: Option<number>[];
        classSubjects: Option<number>[];
        student: Option<number> | null;
    };
    studentOptions?: Option<number>[];
    result: Result | null;
    formats: string[];
    can: { export: boolean };
}) {
    const [form, setForm] = useState<Partial<Record<FilterKey, string>>>(values);
    const uses = (filter: FilterKey) => report.filters.includes(filter);
    const isRequired = (filter: FilterKey) => report.required.includes(filter);
    const set = (filter: FilterKey, value: string) => setForm({ ...form, [filter]: value });

    const query = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    const missing = report.required.filter((filter) => !form[filter]);

    const run = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('reports.show', report.key),
            Object.fromEntries(Object.entries(form).filter(([, value]) => value)),
            { preserveState: true, preserveScroll: true },
        );
    };

    const select = (filter: FilterKey, label: string, choices: Option<number>[], placeholder: string) =>
        uses(filter) && (
            <div>
                <label htmlFor={`filter-${filter}`} className="text-sm font-medium">
                    {label}
                    {isRequired(filter) && <span className="text-danger"> *</span>}
                </label>
                <SelectInput
                    id={`filter-${filter}`}
                    value={form[filter] ?? ''}
                    options={choices}
                    placeholder={placeholder}
                    onChange={(e) => set(filter, e.target.value)}
                    className="mt-1 w-full"
                />
            </div>
        );

    return (
        <AuthenticatedLayout>
            <Head title={report.title} />
            <PageHeader title={report.title} intro={report.description} breadcrumbs={[{ label: 'Reports', href: route('reports.index') }, { label: report.title }]} />

            <div className="space-y-6">
                <Card title="Filters">
                    <form onSubmit={run} className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {uses('student_id') && (
                                <StudentPicker
                                    value={form.student_id ?? ''}
                                    initial={options.student}
                                    found={studentOptions}
                                    required={isRequired('student_id')}
                                    onChange={(value) => set('student_id', value)}
                                />
                            )}
                            {select('school_class_id', 'Class', options.classes, isRequired('school_class_id') ? 'Choose a class' : 'All classes')}
                            {select('class_subject_id', 'Subject', options.classSubjects, isRequired('class_subject_id') ? 'Choose a subject' : 'Daily attendance')}
                            {select('academic_year_id', 'Academic year', options.academicYears, 'All years')}
                            {select('academic_term_id', 'Term', options.academicTerms, 'All terms')}
                            {uses('from') && (
                                <div>
                                    <label htmlFor="filter-from" className="text-sm font-medium">
                                        From
                                    </label>
                                    <TextInput id="filter-from" type="date" value={form.from ?? ''} onChange={(e) => set('from', e.target.value)} className="mt-1 w-full" />
                                </div>
                            )}
                            {uses('to') && (
                                <div>
                                    <label htmlFor="filter-to" className="text-sm font-medium">
                                        To
                                    </label>
                                    <TextInput id="filter-to" type="date" value={form.to ?? ''} onChange={(e) => set('to', e.target.value)} className="mt-1 w-full" />
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={missing.length > 0}>
                                Show report
                            </Button>
                            {missing.length > 0 && <p className="text-sm text-muted">Fill in the fields marked * first.</p>}
                        </div>
                    </form>
                </Card>

                {result && (
                    <Card
                        title={result.title}
                        actions={
                            can.export &&
                            formats.map((format) => (
                                // Plain links: downloads are not page visits.
                                <a
                                    key={format}
                                    href={route('reports.export', { report: report.key, format, ...query })}
                                    className="inline-flex min-h-8 items-center rounded-lg border border-line bg-surface px-3 py-1 text-xs font-semibold whitespace-nowrap hover:bg-canvas"
                                >
                                    Download {formatLabels[format] ?? format}
                                </a>
                            ))
                        }
                        flush
                    >
                        <div className="space-y-1 border-b border-line px-5 py-4 text-sm text-muted">
                            {result.subtitle.map((line) => (
                                <p key={line}>{line}</p>
                            ))}
                            {result.summary.length > 0 && (
                                <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-ink">
                                    {result.summary.map((item) => (
                                        <div key={item.label}>
                                            <dt className="text-xs text-muted">{item.label}</dt>
                                            <dd className="font-display font-semibold">{item.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </div>

                        {result.rows.length === 0 ? (
                            <EmptyState icon="report" title="No records match these filters" />
                        ) : (
                            <>
                                <Table caption={result.title}>
                                    <thead>
                                        <tr>
                                            {result.columns.map((column) => (
                                                <Th key={column}>{column}</Th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {result.rows.map((row, index) => (
                                            <tr key={index}>
                                                {row.map((value, column) => (
                                                    <Td key={column} className={typeof value === 'number' ? 'text-right tabular-nums' : undefined}>
                                                        {value ?? ''}
                                                    </Td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                                {result.total_rows > result.rows.length && (
                                    <p className="px-5 py-3 text-sm text-muted">
                                        Showing the first {result.rows.length} of {result.total_rows} rows. Downloads include every row.
                                    </p>
                                )}
                            </>
                        )}
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

/** Find a student by typing; the server returns matches the person may see. */
function StudentPicker({
    value,
    initial,
    found,
    required,
    onChange,
}: {
    value: string;
    initial: Option<number> | null;
    found?: Option<number>[];
    required: boolean;
    onChange: (value: string) => void;
}) {
    const [search, setSearch] = useState('');
    const [chosen, setChosen] = useState<Option<number> | null>(initial);

    useEffect(() => {
        if (search.trim().length < 2) {
            return;
        }

        const timer = setTimeout(() => router.reload({ only: ['studentOptions'], data: { student_search: search } }), 300);

        return () => clearTimeout(timer);
    }, [search]);

    return (
        <div className="sm:col-span-2 lg:col-span-1">
            <label htmlFor="filter-student" className="text-sm font-medium">
                Student{required && <span className="text-danger"> *</span>}
            </label>
            {chosen && value ? (
                <div className="mt-1 flex items-center justify-between gap-2 rounded-lg border border-brand/30 bg-brand-soft px-3 py-2 text-sm">
                    <span className="truncate">{chosen.label}</span>
                    <button
                        type="button"
                        className="font-medium text-brand hover:underline"
                        onClick={() => {
                            setChosen(null);
                            onChange('');
                        }}
                    >
                        Change
                    </button>
                </div>
            ) : (
                <>
                    <TextInput
                        id="filter-student"
                        type="search"
                        placeholder="Type a name or student number"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="mt-1 w-full"
                        autoComplete="off"
                    />
                    {search.trim().length >= 2 && (
                        <ul className="mt-1 max-h-48 overflow-y-auto rounded-lg border border-line bg-surface" aria-live="polite">
                            {!found?.length ? (
                                <li className="px-3 py-2 text-sm text-muted">No students found.</li>
                            ) : (
                                found.map((option) => (
                                    <li key={option.value}>
                                        <button
                                            type="button"
                                            className="w-full px-3 py-2 text-left text-sm hover:bg-canvas"
                                            onClick={() => {
                                                setChosen(option);
                                                onChange(String(option.value));
                                                setSearch('');
                                            }}
                                        >
                                            {option.label}
                                        </button>
                                    </li>
                                ))
                            )}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
