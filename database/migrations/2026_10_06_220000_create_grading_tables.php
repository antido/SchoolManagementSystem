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
         * A grading scale turns a percentage into a descriptor ("Outstanding")
         * and says what percentage passes. Each band applies from its
         * min_percentage up to the next band.
         */
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('passing_percentage', 5, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('grading_scale_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_percentage', 5, 2);
            $table->string('label');
            $table->timestamps();
        });

        /*
         * A grading system weighs kinds of activity into one grade, e.g.
         * assignments 20%, quizzes 20%, examinations 40%, other work 20%.
         * Class subjects use the default system unless one is chosen.
         */
        Schema::create('grading_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('grading_scale_id')->constrained()->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('grading_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_system_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('weight', 5, 2);
            // The activity types (assignment, quiz, ...) whose scores make up this component.
            $table->json('activity_types');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::table('class_subjects', function (Blueprint $table) {
            $table->foreignId('grading_system_id')->nullable()->after('academic_term_id')->constrained()->restrictOnDelete();
        });

        /*
         * Computed grades. A row is never changed: when a grade is computed
         * again and differs, the old row gets superseded_at and a new row is
         * added, so the history of every grade is kept.
         */
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->string('descriptor');
            $table->boolean('passed');
            $table->json('breakdown');
            $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('computed_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['class_subject_id', 'student_id', 'superseded_at'], 'student_grades_current_index');
            $table->index(['student_id', 'superseded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');

        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grading_system_id');
        });

        Schema::dropIfExists('grading_components');
        Schema::dropIfExists('grading_systems');
        Schema::dropIfExists('grading_scale_bands');
        Schema::dropIfExists('grading_scales');
    }
};
