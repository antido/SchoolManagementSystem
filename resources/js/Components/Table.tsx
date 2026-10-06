import { cn } from '@/Utils/format';
import { PropsWithChildren, ThHTMLAttributes, TdHTMLAttributes } from 'react';

/**
 * A data table that scrolls sideways on small screens instead of
 * squashing its columns. Put it inside a <Card flush>.
 *
 *     <Table caption="Students">
 *         <thead><tr><Th>Name</Th></tr></thead>
 *         <tbody><tr><Td>Ana</Td></tr></tbody>
 *     </Table>
 */
export default function Table({ caption, children }: PropsWithChildren<{ caption: string }>) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-left text-sm">
                <caption className="sr-only">{caption}</caption>
                {children}
            </table>
        </div>
    );
}

export function Th({ className = '', ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
    return (
        <th
            scope="col"
            {...props}
            className={cn(
                'border-b border-line bg-canvas/60 px-5 py-3 text-xs font-semibold tracking-wide text-muted uppercase',
                className,
            )}
        />
    );
}

export function Td({ className = '', ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
    return <td {...props} className={cn('border-b border-line px-5 py-3 align-middle', className)} />;
}
