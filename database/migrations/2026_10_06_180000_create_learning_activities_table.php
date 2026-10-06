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
         * Assignments, quizzes, examinations, questionnaires and other
         * activities given to a class subject. teacher_id is who created it,
         * kept even if the class subject later gets another teacher.
         */
        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->text('instructions')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('total_points', 8, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['class_subject_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_activities');
    }
};
