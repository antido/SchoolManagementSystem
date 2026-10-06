import { cn } from '@/Utils/format';
import { HTMLAttributes } from 'react';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p {...props} className={cn('text-sm font-medium text-danger', className)}>
            {message}
        </p>
    ) : null;
}
