import { inputClasses } from '@/Components/TextInput';
import { cn } from '@/Utils/format';
import { TextareaHTMLAttributes } from 'react';

export default function Textarea({
    className = '',
    rows = 4,
    ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea {...props} rows={rows} className={cn(inputClasses, className)} />;
}
