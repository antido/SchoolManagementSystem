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
        // Whether students may still hand in work after the due date.
        Schema::table('learning_activities', function (Blueprint $table) {
            $table->boolean('allow_late_submissions')->default(true)->after('total_points');
        });

        /*
         * One row per student per activity. Handing in again replaces the
         * work and moves submitted_at; a graded submission is final.
         */
        Schema::create('activity_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->text('body')->nullable();
            $table->string('status', 20);
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_activity_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        /*
         * Files attached to any record (activities, submissions, and later
         * awards and forum posts). The file itself lives on a private disk
         * under a generated name; original_name is only shown, never used
         * as a path.
         */
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('activity_submissions');

        Schema::table('learning_activities', function (Blueprint $table) {
            $table->dropColumn('allow_late_submissions');
        });
    }
};
