import { cn } from '@/Utils/format';
import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={cn('size-5 rounded border-line text-brand shadow-sm', className)}
        />
    );
}
