import Button from '@/Components/Button';
import { ButtonHTMLAttributes } from 'react';

/** A submit button in the primary colour. */
export default function PrimaryButton({
    type = 'submit',
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <Button variant="primary" type={type} {...props}>
            {children}
        </Button>
    );
}
