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
         * Questions on a quiz, examination or questionnaire, in the order
         * students see them. They go with the activity when it is deleted.
         */
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_activity_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->string('question_type', 30);
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['learning_activity_id', 'order']);
        });

        /*
         * The choices for multiple choice, true/false and multiple select
         * questions, and the accepted answers for identification questions
         * (all marked correct).
         */
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
