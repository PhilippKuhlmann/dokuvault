<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Support\ExpiringItems;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    public function __construct(public Collection $items)
    {
        // Names come from users and agents (a hostname is whatever the agent
        // reports). A blank line inside one ended the HTML block of the mail,
        // and the rest was read as markdown: a link or a tracking image in a
        // genuine mail. One line each, then they stay text in the HTML.
        $this->items = $items->map(fn (array $item) => array_merge($item, [
            'name' => Str::squish((string) ($item['name'] ?? '')),
            'customer' => Str::squish((string) ($item['customer'] ?? '')),
            'label' => Str::squish((string) ($item['label'] ?? '')),
        ]));
    }

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
        $silent = $this->items->where('stage', ExpiringItems::SILENT)->count();
        $soon = $this->items->count() - $expired - $silent;

        $silentText = $silent === 1 ? __('1 Agent meldet nicht') : __(':anzahl Agenten melden nicht', ['anzahl' => $silent]);

        // One kind: the full sentence. Several: the short forms side by side,
        // in the order of urgency.
        $subject = match (true) {
            (bool) $expired + (bool) $soon + (bool) $silent > 1 => collect([
                $expired ? __(':anzahl abgelaufen', ['anzahl' => $expired]) : null,
                $soon ? __(':anzahl laufen bald ab', ['anzahl' => $soon]) : null,
                $silent ? $silentText : null,
            ])->filter()->implode(', '),
            $expired > 0 => ($expired === 1 ? __('1 Eintrag ist abgelaufen') : __(':anzahl Einträge sind abgelaufen', ['anzahl' => $expired])),
            $silent > 0 => $silentText,
            default => ($soon === 1 ? __('1 Eintrag läuft bald ab') : __(':anzahl Einträge laufen bald ab', ['anzahl' => $soon])),
        };

        $subject = Setting::appName().': '.$subject;

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.expiry', [
                'app' => Setting::appName(),
                'expired' => $expired,
                'soon' => $soon,
                'silent' => $silent,
                'groups' => $this->items->take(self::MAX_ROWS)->groupBy('customer'),
                'rest' => max(0, $this->items->count() - self::MAX_ROWS),
                'home' => url('/'),
                'profile' => route('profile.edit').'#benachrichtigungen',
            ]);
    }
}
