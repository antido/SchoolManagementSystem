import AwardBadge from '@/Components/AwardBadge';
import Badge from '@/Components/Badge';
import { StudentAward } from '@/Types/awards';
import { cn, formatDate } from '@/Utils/format';
import { Link } from '@inertiajs/react';

/** Awards a student received, newest first. Revoked ones are struck through. */
export default function StudentAwardList({ awards }: { awards: StudentAward[] }) {
    return (
        <ul className="divide-y divide-line">
            {awards.map((studentAward) => (
                <li key={studentAward.id} className="flex items-start gap-3 px-5 py-3">
                    <AwardBadge imageUrl={studentAward.award.image_url} className="size-10" />
                    <div className="min-w-0 flex-1">
                        <p className={cn('font-medium', studentAward.revoked_at && 'text-muted line-through')}>
                            <Link href={route('awards.show', studentAward.award.id)} className="hover:text-brand hover:underline">
                                {studentAward.award.name}
                            </Link>
                        </p>
                        <p className="text-xs text-muted">
                            {formatDate(studentAward.awarded_on)}
                            {studentAward.school_class && `, ${studentAward.school_class.name}`}
                            {studentAward.academic_year && ` (${studentAward.academic_year})`}
                        </p>
                        {studentAward.remarks && <p className="mt-1 text-sm">{studentAward.remarks}</p>}
                        {studentAward.revoked_at && (
                            <p className="mt-1 text-sm text-danger">
                                <Badge tone="danger">Revoked</Badge> {studentAward.revoked_reason}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}
