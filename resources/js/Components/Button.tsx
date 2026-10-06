import { cn } from '@/Utils/format';
import { InertiaLinkProps, Link } from '@inertiajs/react';
import { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';

const base =
    'whitespace-nowrap inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50';

const variants: Record<Variant, string> = {
    primary: 'bg-brand text-white hover:bg-brand-deep',
    secondary:
        'border border-line bg-surface text-ink hover:border-brand hover:text-brand',
    danger: 'bg-danger text-white hover:bg-danger/90',
    ghost: 'text-brand hover:bg-brand-soft',
};

interface CommonProps {
    variant?: Variant;
    /** A compact size for table rows and toolbars. */
    small?: boolean;
    className?: string;
    children: ReactNode;
}

type ButtonProps = CommonProps &
    ButtonHTMLAttributes<HTMLButtonElement> & { href?: undefined };

type LinkProps = CommonProps &
    Omit<InertiaLinkProps, 'className' | 'children'> & { href: string };

/**
 * The one button used across the app. Pass `href` to render a link that
 * looks like a button.
 */
export default function Button(props: ButtonProps | LinkProps) {
    const {
        variant = 'primary',
        small = false,
        className = '',
        children,
        ...rest
    } = props;

    const classes = cn(
        base,
        variants[variant],
        small && 'min-h-8 px-3 py-1 text-xs',
        className,
    );

    if (rest.href !== undefined) {
        return (
            <Link {...(rest as Omit<LinkProps, keyof CommonProps>)} className={classes}>
                {children}
            </Link>
        );
    }

    const { type = 'button', ...buttonProps } = rest as ButtonHTMLAttributes<HTMLButtonElement>;

    return (
        <button type={type} {...buttonProps} className={classes}>
            {children}
        </button>
    );
}
