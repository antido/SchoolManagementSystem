import Button from '@/Components/Button';
import { ButtonHTMLAttributes } from 'react';

/** A quiet button for secondary actions such as "Cancel". */
export default function SecondaryButton({
    type = 'button',
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <Button variant="secondary" type={type} {...props}>
            {children}
        </Button>
    );
}
