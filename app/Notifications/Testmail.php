<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Die Probe aufs Exempel fuer die Mail-Einstellungen.
 *
 * Ohne ShouldQueue, aus demselben Grund wie die Einladung: Wer auf "Testmail
 * senden" drueckt, will jetzt wissen, ob es geht - nicht nachdem ein Worker
 * gelaufen ist.
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
            // Bewusst ohne Zeitstempel: Die Anwendung rechnet in UTC, im
            // Postfach stuende dann eine Uhrzeit, die um den Zeitzonenversatz
            // danebenliegt. Das Datum der Mail steht ohnehin im Kopf, und dort
            // stimmt es.
            ->line(__('Diese Nachricht kommt von :anwendung. Der Mailversand ist damit richtig eingestellt.', ['anwendung' => $name]));
    }
}
