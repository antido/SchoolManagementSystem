import { inputClasses } from '@/Components/TextInput';
import { Option } from '@/Types';
import { cn } from '@/Utils/format';
import { SelectHTMLAttributes } from 'react';

interface Props extends SelectHTMLAttributes<HTMLSelectElement> {
    /** The choices. Use `children` instead for custom option markup. */
    options?: Option<string | number>[];
    /** Text for an empty first option, for example "All statuses". */
    placeholder?: string;
}

export default function SelectInput({
    className = '',
    options,
    placeholder,
    children,
    ...props
}: Props) {
    return (
        <select {...props} className={cn(inputClasses, className)}>
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {options?.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
            {children}
        </select>
    );
}
