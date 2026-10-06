import { cn } from '@/Utils/format';
import { LabelHTMLAttributes } from 'react';

export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}: LabelHTMLAttributes<HTMLLabelElement> & { value?: string }) {
    return (
        <label {...props} className={cn('block text-sm font-medium text-ink', className)}>
            {value ? value : children}
        </label>
    );
}
