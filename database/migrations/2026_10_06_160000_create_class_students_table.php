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
         * Enrollments: which students are in which class, and when.
         * Rows are never deleted; leaving a class sets the status to
         * "withdrawn" and fills ended_at, so the history stays complete.
         */
        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Copied from the class, so history stays filterable by year.
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['school_class_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_students');
    }
};
