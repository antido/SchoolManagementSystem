<?php

namespace App\Services;

use App\Events\AwardGranted;
use App\Models\Award;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Awards and the students who receive them.
 */
class AwardService
{
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * Create or update an award, replacing or removing its image.
     *
     * @param  array<string, mixed>  $data  validated AwardRequest data
     */
    public function save(?Award $award, array $data, ?UploadedFile $image, bool $removeImage, User $user): Award
    {
        return DB::transaction(function () use ($award, $data, $image, $removeImage, $user) {
            $award ??= new Award;
            $award->fill(collect($data)->only(['name', 'description', 'criteria', 'status'])->all());
            $award->save();

            if ($image !== null || $removeImage) {
                $this->attachments->detachAll($award);
            }

            if ($image !== null) {
                $this->attachments->attach($award, [$image], $user, 'awards');
            }

            return $award;
        });
    }

    public function delete(Award $award): void
    {
        $this->attachments->detachAll($award);
        $award->delete();
    }

    /**
     * Give the award to a student. Listeners notify the student.
     *
     * @param  array{academic_year_id?: int|null, school_class_id?: int|null, awarded_on: string, remarks?: string|null}  $data
     */
    public function grant(Award $award, Student $student, array $data, User $grantedBy): StudentAward
    {
        $studentAward = new StudentAward;
        $studentAward->award_id = $award->id;
        $studentAward->student_id = $student->id;
        $studentAward->academic_year_id = $data['academic_year_id'] ?? null;
        $studentAward->school_class_id = $data['school_class_id'] ?? null;
        $studentAward->awarded_on = $data['awarded_on'];
        $studentAward->remarks = $data['remarks'] ?? null;
        $studentAward->awarded_by = $grantedBy->id;
        $studentAward->save();

        AwardGranted::dispatch($studentAward);

        return $studentAward;
    }

    /**
     * Take back an award given by mistake. The record stays, marked revoked.
     */
    public function revoke(StudentAward $studentAward, string $reason, User $revokedBy): void
    {
        $studentAward->revoked_at = now();
        $studentAward->revoked_reason = $reason;
        $studentAward->revoked_by = $revokedBy->id;
        $studentAward->save();
    }
}
