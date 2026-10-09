<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Support\ExpiringItems;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The daily expiry mail: what newly expires soon or has just expired.
 *
 * One mail per recipient and day, not one per certificate - ten mails on a
 * Monday morning are read as spam, one list is read.
 *
 * Without ShouldQueue: the command runs from the scheduler anyway, and a
 * failed send has to be noticed there, before it records the items as told.
 */
class ExpiryNotice extends Notification
{
    /** More rows than this do not belong in a mail - the rest is in DokuVault. */
    public const MAX_ROWS = 100;

    public function __construct(public Collection $items) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expired = $this->items->where('stage', ExpiringItems::EXPIRED)->count();
        $soon = $this->items->count() - $expired;

        $subject = match (true) {
            $expired && $soon => __(':abgelaufen abgelaufen, :bald laufen bald ab', ['abgelaufen' => $expired, 'bald' => $soon]),
            $expired > 0 => ($expired === 1 ? __('1 Eintrag ist abgelaufen') : __(':anzahl Einträge sind abgelaufen', ['anzahl' => $expired])),
            default => ($soon === 1 ? __('1 Eintrag läuft bald ab') : __(':anzahl Einträge laufen bald ab', ['anzahl' => $soon])),
        };

        $subject = Setting::appName().': '.$subject;

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.expiry', [
                'app' => Setting::appName(),
                'expired' => $expired,
                'soon' => $soon,
                'groups' => $this->items->take(self::MAX_ROWS)->groupBy('customer'),
                'rest' => max(0, $this->items->count() - self::MAX_ROWS),
                'home' => url('/'),
                'profile' => route('profile.edit').'#benachrichtigungen',
            ]);
    }
}
