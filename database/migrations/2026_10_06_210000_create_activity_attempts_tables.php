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
        // How quizzes, examinations and questionnaires are taken.
        Schema::table('learning_activities', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_attempts')->default(1)->after('allow_late_submissions');
            $table->boolean('shuffle_questions')->default(false)->after('max_attempts');
            $table->boolean('shuffle_options')->default(false)->after('shuffle_questions');
        });

        /*
         * One sitting of a quiz, examination or questionnaire by a student.
         * expires_at is fixed when the attempt starts (the time limit, cut
         * short by the due date), and the server refuses answers after it.
         * The question and option order each student saw is kept so a
         * resumed attempt looks the same.
         */
        Schema::create('activity_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->decimal('score', 8, 2)->nullable();
            $table->json('question_order');
            $table->json('option_order')->nullable();
            $table->timestamps();

            $table->unique(['learning_activity_id', 'student_id', 'attempt_number'], 'activity_attempts_student_attempt_unique');
            $table->index(['status', 'expires_at']);
        });

        /*
         * A student's answer to one question in an attempt. points_awarded is
         * filled automatically for objective questions and by the teacher
         * for short answers and essays.
         */
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->json('selected_options')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_awarded', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['activity_attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('activity_attempts');

        Schema::table('learning_activities', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'shuffle_questions', 'shuffle_options']);
        });
    }
};
