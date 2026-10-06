import Card from '@/Components/Card';

/** A short list of "label ... number" rows inside a card. */
export default function SummaryList({
    title,
    items,
}: {
    title: string;
    items: { label: string; value: string | number }[];
}) {
    return (
        <Card title={title} flush>
            <dl className="divide-y divide-line">
                {items.map((item) => (
                    <div key={item.label} className="flex items-center justify-between gap-4 px-5 py-3">
                        <dt className="text-sm text-muted">{item.label}</dt>
                        <dd className="font-display font-semibold tabular-nums">{item.value}</dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}
