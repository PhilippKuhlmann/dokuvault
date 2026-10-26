<?php

namespace App\Support;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistSetting;
use RuntimeException;

/**
 * The PowerShell script that does the AD part of a checklist.
 *
 * Onboarding: create the user in the default container, add the groups of
 * the chosen drives and programs. Offboarding: disable the user, reset the
 * password, remove the groups. Afterwards the script reports to
 * /api/checklist-report; the documentation ticks the items off and stores
 * the AD user with its password.
 *
 * The download is a .cmd that carries the PowerShell part. Windows marks a
 * downloaded .ps1 as "from the internet", and the default execution policy
 * (RemoteSigned) refuses to run it unsigned - no line of it would run. The
 * policy applies to script files only: the .cmd elevates itself, reads the
 * PowerShell part and runs it as a script block.
 *
 * What the script may touch is fixed here: open items with an action whose
 * approval (if needed) is given. The data goes in as JSON, so no value from
 * the documentation ends up as PowerShell code. The one exception is the
 * name in the comment block at the top - see commentText() and
 * assertStructure(), which keep it from ever leaving that comment.
 */
class ChecklistAdScript
{
    public static function filename(Checklist $checklist): string
    {
        return $checklist->type.'-'.($checklist->username ?: 'benutzer').'.cmd';
    }

    /**
     * cmd.exe runs this part; for PowerShell it is a comment block. The
     * first line is a no-op for cmd ("<#" as input, ":" as label).
     */
    private const CMD_HEADER = <<<'CMD'
<# : DokuVault - der Teil fuer cmd.exe, PowerShell ueberspringt ihn
@echo off
net session >nul 2>&1
if errorlevel 1 (
    echo Starte neu mit Administratorrechten ...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)
set "DOKUVAULT_SCRIPT=%~f0"
powershell -NoProfile -ExecutionPolicy Bypass -Command "& ([scriptblock]::Create([IO.File]::ReadAllText($env:DOKUVAULT_SCRIPT)))"
exit /b
#>

CMD;

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
            '__TITLE__' => self::commentText($checklist->fullName()),
            '__EXPIRES__' => $checklist->script_token_expires_at?->format('d.m.Y H:i') ?? '',
        ]);
        self::assertStructure($template, $script);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // The JSON sits in a @' ... '@ here-string, which only a line starting
        // with '@ ends. Pretty-printed JSON never starts a line that way -
        // checked anyway, since this script runs elevated.
        if (preg_match("/^'@/m", $json)) {
            throw new RuntimeException('Checklist data would end the here-string of the AD script.');
        }
        $script = str_replace('__DATA__', $json, $script);

        // #Requires is for script files; in a script block Import-Module does it.
        $script = preg_replace('/^#Requires[^\n]*\n/', '', $script);

        // No BOM: cmd.exe would choke on it in the first line. PowerShell
        // reads the file as UTF-8 itself. CRLF, as cmd.exe expects.
        return preg_replace("/\r?\n/", "\r\n", self::CMD_HEADER.$script);
    }

    /**
     * Text for the comment block at the top of the script. An allowlist, not
     * a blocklist: removing "#>" once turned "##>>" back into "#>", which
     * ended the comment - and the rest of a customer-entered name ran as
     * code, elevated, on a domain controller. Letters (with umlauts and
     * accents), digits, space and . _ - ' are all a name needs.
     */
    public static function commentText(?string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{M}\p{N} ._\'-]/u', '', (string) $text) ?? '';

        return mb_substr(trim(preg_replace('/ {2,}/', ' ', $text)), 0, 200);
    }

    /**
     * Last line of defence: filling in the values must not add or remove a
     * single comment delimiter. If it did, a value has changed what is code
     * and what is comment - better no script than that one.
     */
    private static function assertStructure(string $template, string $script): void
    {
        foreach (['<#', '#>'] as $delimiter) {
            if (substr_count($script, $delimiter) !== substr_count($template, $delimiter)) {
                throw new RuntimeException('A value would change the comment structure of the AD script.');
            }
        }
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
