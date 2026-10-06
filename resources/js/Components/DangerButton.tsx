import Button from '@/Components/Button';
import { ButtonHTMLAttributes } from 'react';

/** A button for destructive actions such as "Delete". */
export default function DangerButton({
    type = 'submit',
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <Button variant="danger" type={type} {...props}>
            {children}
        </Button>
    );
}
