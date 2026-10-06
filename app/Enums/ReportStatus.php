<?php

namespace App\Enums;

/**
 * Where a report about a forum post is.
 */
enum ReportStatus: string
{
    /** Waiting for a moderator. */
    case Pending = 'pending';

    /** A moderator acted on it (removed the post). */
    case Resolved = 'resolved';

    /** A moderator found nothing wrong. */
    case Dismissed = 'dismissed';
}
