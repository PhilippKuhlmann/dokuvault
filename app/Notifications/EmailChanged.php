<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Goes to the old address when a user changes their email in the profile.
 * The email is where password resets go - whoever changes it behind the
 * owner's back owns the account. This way the owner at least finds out.
 */
class EmailChanged extends Notification
{
    public function __construct(private string $name, private string $newEmail) {}

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
            ->subject(__('Ihre E-Mail-Adresse bei :anwendung wurde geändert', ['anwendung' => $app]))
            ->greeting(__('Hallo :name,', ['name' => $this->name]))
            ->line(__('die E-Mail-Adresse Ihres Zugangs zu :anwendung wurde gerade auf :neu geändert.', ['anwendung' => $app, 'neu' => $this->newEmail]))
            ->line(__('Waren Sie das nicht, wenden Sie sich bitte sofort an Ihren Administrator.'));
    }
}
