<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnrollStudentsRequest;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;

/**
 * Enrolling students is done from the class page; withdrawing from the
 * class roster. Both send the user back to that page.
 */
class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments) {}

    public function store(EnrollStudentsRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $result = $this->enrollments->enroll($schoolClass, $request->validated('student_ids'));

        $message = match (true) {
            $result['enrolled'] === 0 => 'No students were enrolled. They are either inactive or already in this class.',
            $result['skipped'] > 0 => "{$result['enrolled']} enrolled. {$result['skipped']} skipped because they are inactive or already in this class.",
            default => $result['enrolled'] === 1 ? '1 student enrolled.' : "{$result['enrolled']} students enrolled.",
        };

        return back()->with($result['enrolled'] === 0 ? 'error' : 'success', $message);
    }

    public function withdraw(Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('withdraw', $enrollment);

        $this->enrollments->withdraw($enrollment);

        $enrollment->load('student');

        return back()->with('success', "{$enrollment->student->full_name} was withdrawn. The enrollment stays in their history.");
    }
}
