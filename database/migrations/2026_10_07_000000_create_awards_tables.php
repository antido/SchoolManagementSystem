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
         * The kinds of award the school gives, such as Academic Excellence.
         * An optional image is stored as an attachment.
         */
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->text('criteria')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        /*
         * An award given to a student. Rows are never deleted: an award
         * given by mistake is revoked, so the history stays complete.
         */
        Schema::create('student_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('awarded_on');
            $table->text('remarks')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_awards');
        Schema::dropIfExists('awards');
    }
};
