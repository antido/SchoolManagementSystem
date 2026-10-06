<?php

namespace App\Notifications;

/**
 * An announcement written by staff and sent to a group of people.
 */
class AnnouncementNotification extends SchoolNotification
{
    public function __construct(
        private readonly string $subject,
        private readonly string $message,
        private readonly string $sender,
    ) {}

    protected function title(): string
    {
        return $this->subject;
    }

    protected function body(): ?string
    {
        return "{$this->message}\n\nFrom {$this->sender}";
    }

    protected function url(): string
    {
        return route('notifications.index', [], false);
    }

    protected function icon(): string
    {
        return 'bell';
    }
}
