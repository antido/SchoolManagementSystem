import { cn } from '@/Utils/format';
import {
    forwardRef,
    InputHTMLAttributes,
    useEffect,
    useImperativeHandle,
    useRef,
} from 'react';

/** Shared look for text inputs, selects and text areas. */
export const inputClasses =
    'rounded-lg border-line bg-surface text-ink shadow-sm placeholder:text-muted disabled:bg-canvas disabled:text-muted aria-[invalid=true]:border-danger';

export default forwardRef(function TextInput(
    {
        type = 'text',
        className = '',
        isFocused = false,
        ...props
    }: InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean },
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={cn(inputClasses, className)}
            ref={localRef}
        />
    );
});
