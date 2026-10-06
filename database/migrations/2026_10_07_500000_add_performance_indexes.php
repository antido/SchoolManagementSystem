<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the queries that run most often or over the largest tables.
     */
    public function up(): void
    {
        // The unread count on every page, and each person's newest notifications.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_index');
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_latest_index');
        });

        // Opening one attendance sheet: a class, a day, daily or one subject.
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['school_class_id', 'attendance_date', 'class_subject_id'], 'attendances_sheet_index');
        });

        // The latest topic of each forum category.
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->index(['forum_category_id', 'last_posted_at'], 'forum_topics_latest_index');
        });

        // Enrollment counts per academic year on the dashboard.
        Schema::table('class_students', function (Blueprint $table) {
            $table->index(['academic_year_id', 'status'], 'class_students_year_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('class_students', fn (Blueprint $table) => $table->dropIndex('class_students_year_status_index'));
        Schema::table('forum_topics', fn (Blueprint $table) => $table->dropIndex('forum_topics_latest_index'));
        Schema::table('attendances', fn (Blueprint $table) => $table->dropIndex('attendances_sheet_index'));
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_latest_index');
            $table->dropIndex('notifications_unread_index');
        });
    }
};
