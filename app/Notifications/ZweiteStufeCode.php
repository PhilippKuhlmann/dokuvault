<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The code that confirms setting up the second factor. Whoever only knows
 * the password could otherwise set it up first and lock the owner out.
 *
 * Without ShouldQueue: the user is waiting for it on the profile page.
 */
class ZweiteStufeCode extends Notification
{
    public function __construct(public readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = Setting::appName();

        return (new MailMessage)
            ->subject(__('Ihr Code für die zweite Stufe bei :anwendung', ['anwendung' => $app]))
            ->greeting(__('Hallo :name,', ['name' => preg_replace('/\s+/', ' ', trim((string) $notifiable->name))]))
            ->line(__('mit diesem Code bestätigen Sie, dass Sie die zweite Stufe für Ihren Zugang einrichten:'))
            ->line($this->code)
            ->line(__('Er gilt 15 Minuten. Haben Sie nichts eingerichtet, kennt jemand Ihr Kennwort – ändern Sie es und wenden Sie sich an Ihren Administrator.'));
    }
}
