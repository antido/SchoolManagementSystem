<?php

namespace App\Events;

use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A teacher was attached to a class (as adviser or teacher) or given a
 * class subject to teach.
 */
class TeacherAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClassTeacher|ClassSubject $assignment) {}
}
