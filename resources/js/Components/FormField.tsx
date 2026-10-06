import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { ReactElement, cloneElement } from 'react';

interface Props {
    /** The id of the input inside. The label points at it. */
    id: string;
    label: string;
    error?: string;
    /** A short help text shown under the input. */
    hint?: string;
    required?: boolean;
    className?: string;
    children: ReactElement<Record<string, unknown>>;
}

/**
 * A label, an input, an optional hint and the validation error, wired
 * together for screen readers:
 *
 *     <FormField id="email" label="Email" error={errors.email}>
 *         <TextInput type="email" value={data.email} onChange={...} />
 *     </FormField>
 *
 * The input receives the id, aria-invalid and aria-describedby itself.
 */
export default function FormField({
    id,
    label,
    error,
    hint,
    required = false,
    className = '',
    children,
}: Props) {
    const describedBy = [hint && `${id}-hint`, error && `${id}-error`]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={className}>
            <InputLabel htmlFor={id}>
                {label}
                {required && (
                    <span className="text-danger" aria-hidden="true">
                        {' '}
                        *
                    </span>
                )}
            </InputLabel>

            {cloneElement(children, {
                id,
                required,
                'aria-invalid': error ? true : undefined,
                'aria-describedby': describedBy || undefined,
                className: `mt-1 block w-full ${(children.props.className as string) ?? ''}`,
            })}

            {hint && (
                <p id={`${id}-hint`} className="mt-1.5 text-sm text-muted">
                    {hint}
                </p>
            )}
            <InputError id={`${id}-error`} message={error} className="mt-1.5" />
        </div>
    );
}
