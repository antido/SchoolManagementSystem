<?php

/*
 * Settings for the school system. Values come from .env where it makes sense
 * to change them per installation.
 */
return [

    /*
     * Whether people may create their own account at /register.
     * Schools usually create accounts for students and staff themselves,
     * so this is off by default.
     */
    'registration_enabled' => (bool) env('SCHOOL_REGISTRATION_ENABLED', false),

    /*
     * Whether the FAQ page can be read without signing in. When off, only
     * signed-in users with the faqs.view permission can read it.
     */
    'faq_public' => (bool) env('SCHOOL_FAQ_PUBLIC', true),

    /*
     * Uploaded files (assignment materials, student work). They are stored on
     * the private "local" disk and only downloaded through the app, which
     * checks who is asking.
     */
    'attachments' => [
        'disk' => env('SCHOOL_ATTACHMENTS_DISK', 'local'),
        // Largest file allowed, in kilobytes.
        'max_kilobytes' => (int) env('SCHOOL_ATTACHMENT_MAX_KB', 10240),
        // Most files allowed on one activity or submission.
        'max_files' => 5,
        'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'zip'],
    ],

    /*
     * Realtime notifications. When on, new notifications are also pushed to
     * the browser over Laravel Reverb (php artisan reverb:start must be
     * running and BROADCAST_CONNECTION=reverb). Notifications are always
     * saved in the database first; without realtime the page checks for
     * new ones every minute instead.
     */
    'realtime' => (bool) env('SCHOOL_REALTIME', false),

    /*
     * Queries slower than this many milliseconds are written to the log.
     * Set to 0 to turn the check off.
     */
    'slow_query_ms' => (int) env('SCHOOL_SLOW_QUERY_MS', 500),

    /*
     * The audit trail. IP addresses are personal data, so they are only
     * recorded when the school's privacy policy says it does so.
     */
    'audit' => [
        'store_ip' => (bool) env('SCHOOL_AUDIT_STORE_IP', false),
    ],

];
