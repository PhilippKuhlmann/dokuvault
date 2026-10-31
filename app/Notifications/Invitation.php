<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation to a new user: a link behind which they set their own
 * password.
 *
 * Deliberately without ShouldQueue. An administrator pressing "invite" should
 * learn at once whether the mail went out - and not the user first, waiting
 * three days for nothing.
 */
class Invitation extends Notification
{
    public function __construct(private string $token) {}

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
        // Names come from the admin form; keep them on one line in the mail.
        $empfaenger = preg_replace('/\s+/', ' ', trim((string) $notifiable->name));

        // It says "set password" here, on the form behind it and in the
        // confirmation. The mail said "vergeben", the form said "festlegen",
        // the confirmation "setzen" - the same act under three names.
        return (new MailMessage)
            ->subject(__('Ihr Zugang zu :anwendung', ['anwendung' => $name]))
            ->greeting(__('Hallo :name,', ['name' => $empfaenger]))
            ->line(__('Sie haben jetzt Zugang zu :anwendung. Es fehlt nur noch Ihr Kennwort.', ['anwendung' => $name]))
            ->action(__('Kennwort festlegen'), route('invitation.form', [
                'token' => $this->token,
                'username' => $notifiable->username,
            ]))
            ->line(__('Ihr Benutzername: :username', ['username' => $notifiable->username]))
            // The deadline sits in config/auth.php with the broker "invitation".
            ->line(__('Der Link gilt eine Woche. Ist er abgelaufen, fragen Sie Ihren Administrator nach einer neuen Einladung.'));
    }
}
