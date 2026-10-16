<?php

namespace App\Support;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistSetting;

/**
 * The PowerShell script that does the AD part of a checklist.
 *
 * Onboarding: create the user in the default container, add the groups of
 * the chosen drives and programs. Offboarding: disable the user, reset the
 * password, remove the groups. Afterwards the script reports to
 * /api/checklist-report; the documentation ticks the items off and stores
 * the AD user with its password.
 *
 * What the script may touch is fixed here: open items with an action whose
 * approval (if needed) is given. The data goes in as JSON, so no value from
 * the documentation ends up as PowerShell code.
 */
class ChecklistAdScript
{
    public static function filename(Checklist $checklist): string
    {
        return $checklist->type.'-'.($checklist->username ?: 'benutzer').'.ps1';
    }

    /** Items the script would do, and those it leaves out because they wait for approval. */
    public static function plan(Checklist $checklist): array
    {
        $open = $checklist->items->filter(fn (ChecklistItem $item) => $item->action && ! $item->isDone());

        return [
            'items' => $open->reject->awaitsApproval()->values(),
            'waiting' => $open->filter->awaitsApproval()->values(),
        ];
    }

    public static function render(Checklist $checklist, string $token): string
    {
        $plan = self::plan($checklist)['items'];
        $ids = fn (array $actions) => $plan->whereIn('action', $actions)->pluck('id')->values()->all();
        $groups = $plan->whereIn('action', ['add_group', 'remove_group'])->filter(fn ($item) => filled($item->reference))
            ->map(fn ($item) => ['name' => $item->reference, 'item' => $item->id])->values()->all();

        $setting = ChecklistSetting::for($checklist->customer);

        $data = $checklist->isOnboarding()
            ? [
                'user' => [
                    'username' => $checklist->username,
                    'firstName' => $checklist->first_name,
                    'lastName' => $checklist->last_name,
                    'displayName' => $checklist->fullName(),
                    'upn' => self::upn($checklist),
                    'email' => $checklist->email,
                    'changePasswordAtLogon' => $setting->isFixed() || $checklist->force_password_change,
                ],
                'password' => $setting->scriptRule(),
                'createItems' => $ids(['create_ad_user', 'set_password']),
                'groups' => $groups,
            ]
            : [
                'user' => ['username' => $checklist->username],
                'disableItems' => $ids(['disable_ad_user']),
                'resetItems' => $ids(['reset_password']),
                'groups' => $groups,
            ];

        $template = file_get_contents(resource_path('agents/checklist-'.$checklist->type.'.ps1'));
        $script = strtr($template, [
            '__API_URL__' => rtrim(config('app.url'), '/').'/api/checklist-report',
            '__TOKEN__' => $token,
            // Stands in a comment block: no line break, no "#>" that would end it.
            '__TITLE__' => str_replace(['#>', "\r", "\n"], '', $checklist->fullName()),
            '__EXPIRES__' => $checklist->script_token_expires_at?->format('d.m.Y H:i') ?? '',
            '__DATA__' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ]);

        // Windows PowerShell 5.1 reads a file without BOM as ANSI - umlauts
        // in names would break. CRLF, as Notepad expects.
        return "\u{FEFF}".preg_replace("/\r?\n/", "\r\n", $script);
    }

    /**
     * The sign-in name: the email when the request named one, else
     * username@ first UPN suffix or the AD domain itself.
     */
    private static function upn(Checklist $checklist): string
    {
        if ($checklist->email) {
            return $checklist->email;
        }

        $domain = $checklist->customer->addomains()->orderBy('id')->first();
        $suffix = collect(preg_split('/[\s,;]+/', (string) $domain?->upn_suffixes))->filter()->first()
            ?? $domain?->domain;

        return $checklist->username.($suffix ? '@'.$suffix : '');
    }
}
