import { SVGAttributes } from 'react';

/**
 * Line icons drawn on a 24x24 grid. Add a new icon by adding its path here.
 * Icons are decorative: put the meaning in nearby text or an aria-label.
 */
const paths = {
    home: 'M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3v-10.5Z',
    users: 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 19v-1a4 4 0 0 0-3-3.87M15 3.13a3.5 3.5 0 0 1 0 6.75',
    student: 'M12 4 2 9l10 5 10-5-10-5ZM6 11.2V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-4.8M22 9v6',
    teacher: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1',
    calendar: 'M4 6h16v15H4V6ZM4 10h16M8 3v4M16 3v4',
    class: 'M3 5h18v12H3V5ZM8 21h8M12 17v4',
    book: 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5ZM4 19a2 2 0 0 1 2-2h13',
    clipboard: 'M9 4h6v3H9V4ZM7 5H5v16h14V5h-2M9 12h6M9 16h4',
    exam: 'M6 3h9l4 4v14H6V3ZM14 3v5h5M9 13l2 2 4-4',
    chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    check: 'M5 12.5 10 17l9-10',
    award: 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12ZM8.5 14 7 22l5-3 5 3-1.5-8',
    bell: 'M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7ZM10 20a2 2 0 0 0 4 0',
    chat: 'M4 5h16v11H9l-5 4V5Z',
    help: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.5',
    report: 'M6 3h9l4 4v14H6V3ZM9 17v-3M12 17v-6M15 17v-4',
    shield: 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z',
    log: 'M5 4h14v16H5V4ZM8 8h8M8 12h8M8 16h5',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z',
    menu: 'M4 7h16M4 12h16M4 17h16',
    close: 'M6 6l12 12M18 6 6 18',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM20 20l-4-4',
    plus: 'M12 5v14M5 12h14',
    chevronRight: 'M9 6l6 6-6 6',
    logout: 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11',
};

export type IconName = keyof typeof paths;

export default function Icon({
    name,
    className = 'size-5',
    ...props
}: SVGAttributes<SVGElement> & { name: IconName }) {
    return (
        <svg
            {...props}
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={paths[name]} />
        </svg>
    );
}
