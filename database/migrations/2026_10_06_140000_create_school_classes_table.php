<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The table is called school_classes because "class" is a reserved word
     * in PHP; the model is App\Models\SchoolClass.
     */
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            // A year that still has classes cannot be deleted.
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 30);
            $table->string('grade_level', 30);
            $table->string('section', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            // The same code can be reused in another year, but not twice in one year.
            $table->unique(['academic_year_id', 'code']);
            $table->index(['academic_year_id', 'grade_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
