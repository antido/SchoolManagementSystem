import { SVGAttributes } from 'react';

/** The school mark: an open book under a pencil-yellow sun. */
export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" fill="none" aria-hidden="true">
            <rect width="32" height="32" rx="8" fill="#1e4e8c" />
            <circle cx="16" cy="10" r="3.5" fill="#f2a33a" />
            <path
                d="M6 16.5c3.5-1.4 7-1 10 1.2 3-2.2 6.5-2.6 10-1.2V25c-3.5-1.4-7-1-10 1.2C13 24 9.5 23.6 6 25v-8.5Z"
                fill="#ffffff"
            />
            <path d="M16 17.7v8.5" stroke="#1e4e8c" strokeWidth="1.4" />
        </svg>
    );
}
