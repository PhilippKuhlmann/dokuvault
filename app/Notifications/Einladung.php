<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Die Einladung an einen neuen Benutzer: ein Link, hinter dem er sich sein
 * Kennwort selbst gibt.
 *
 * Bewusst ohne ShouldQueue. Ein Administrator, der auf "Einladen" drueckt,
 * soll sofort erfahren, ob die Mail hinausging - und nicht erst der Benutzer,
 * der drei Tage lang auf nichts wartet.
 */
class Einladung extends Notification
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

        // "Kennwort festlegen" heisst es hier, auf dem Formular dahinter und
        // in der Bestaetigung. Die Mail sagte "vergeben", das Formular
        // "setzen" - dreimal dieselbe Handlung unter drei Namen.
        return (new MailMessage)
            ->subject(__('Ihr Zugang zu :anwendung', ['anwendung' => $name]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line(__('Sie haben jetzt Zugang zu :anwendung. Es fehlt nur noch Ihr Kennwort.', ['anwendung' => $name]))
            ->action(__('Kennwort festlegen'), route('einladung.formular', [
                'token' => $this->token,
                'username' => $notifiable->username,
            ]))
            ->line(__('Ihr Benutzername: :username', ['username' => $notifiable->username]))
            // Die Frist steht in config/auth.php beim Broker "einladung".
            ->line(__('Der Link gilt eine Woche. Ist er abgelaufen, fragen Sie Ihren Administrator nach einer neuen Einladung.'));
    }
}
