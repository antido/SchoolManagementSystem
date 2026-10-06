<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A notification stored in the database and shown in the app's
 * notification menu and notification centre.
 *
 * Every notification stores the same fields, so the page can show any of
 * them without knowing its kind:
 * - title: one line, e.g. "New assignment: Fractions worksheet";
 * - body: optional detail;
 * - url: where clicking it goes (a path inside the app);
 * - icon: an icon name the frontend knows.
 *
 * They are created by the queued SendSchoolNotifications listener and the
 * SendAnnouncement job. With config('school.realtime') on they are also
 * broadcast on the person's private channel (see routes/channels.php).
 */
abstract class SchoolNotification extends Notification
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function url(): string;

    protected function body(): ?string
    {
        return null;
    }

    protected function icon(): string
    {
        return 'bell';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // The database copy is what people read; the broadcast only tells an
        // open page that something new arrived.
        return config('school.realtime') ? ['database', 'broadcast'] : ['database'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['title' => $this->title()]);
    }

    /**
     * @return array{title: string, body: string|null, url: string, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'icon' => $this->icon(),
        ];
    }
}
