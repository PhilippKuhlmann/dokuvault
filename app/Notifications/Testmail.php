<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The proof of the pudding for the mail settings.
 *
 * Without ShouldQueue, for the same reason as the invitation: whoever presses
 * "send test mail" wants to know now whether it works - not after a worker
 * has run.
 */
class Testmail extends Notification
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
        $name = Setting::appName();

        return (new MailMessage)
            ->subject(__('Testmail von :anwendung', ['anwendung' => $name]))
            ->greeting(__('Das hat geklappt.'))
            // Deliberately without a timestamp: the application computes in
            // UTC, so the mailbox would show a time off by the timezone
            // offset. The date of the mail is in its header anyway, and there
            // it is right.
            ->line(__('Diese Nachricht kommt von :anwendung. Der Mailversand ist damit richtig eingestellt.', ['anwendung' => $name]));
    }
}
