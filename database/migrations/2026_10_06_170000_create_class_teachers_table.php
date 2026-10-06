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
         * Teachers attached to a class (as adviser or teacher) in an academic
         * year. Ending an assignment fills ended_at instead of deleting the
         * row, so past assignments stay visible.
         */
        Schema::create('class_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['school_class_id', 'ended_at']);
            $table->index(['teacher_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_teachers');
    }
};
