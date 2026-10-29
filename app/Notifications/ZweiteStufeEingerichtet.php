<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Goes out after the second factor was set up - so the owner notices one they did not set up. */
class ZweiteStufeEingerichtet extends Notification
{
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
            ->subject(__('Zweite Stufe bei :anwendung eingerichtet', ['anwendung' => $app]))
            ->greeting(__('Hallo :name,', ['name' => preg_replace('/\s+/', ' ', trim((string) $notifiable->name))]))
            ->line(__('für Ihren Zugang zu :anwendung wurde gerade die zweite Stufe eingerichtet.', ['anwendung' => $app]))
            ->line(__('Waren Sie das nicht, wenden Sie sich bitte sofort an Ihren Administrator.'));
    }
}
