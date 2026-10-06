<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * One row per student per day, either for the whole class (daily
         * attendance, class_subject_id empty) or for one subject. There is
         * at most one row per student, class, subject and day; the
         * AttendanceService keeps it that way, since a unique index would
         * not cover the empty subject.
         */
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20);
            $table->string('remarks', 500)->nullable();
            $table->timestamps();

            $table->index(['school_class_id', 'attendance_date']);
            $table->index(['student_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
