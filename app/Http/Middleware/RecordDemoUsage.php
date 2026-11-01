<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Counts visits on the demo instance.
 *
 * Written into a file under storage/, not into the database: the
 * loescht `demo:reset` stuendlich komplett. Dateien unter storage/ ueberstehen
 * both the reset and the `git reset --hard` during a deploy.
 *
 * No user agent, no pages visited. Visits are told apart through a random
 * value in the session that disappears with it.
 *
 * The origin is recorded according to custom.demo_ip_logging, by default
 * gekuerzt (siehe adresse()).
 */
class RecordDemoUsage
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! config('app.demo')) {
            return $response;
        }

        // Real page views only: POSTs, Livewire updates and background
        // requests would otherwise bloat the numbers.
        if (! $request->isMethod('GET') || $request->ajax() || $request->is('livewire/*')) {
            return $response;
        }

        try {
            $this->record($request);
        } catch (\Throwable $e) {
            // Statistics must never slow the demo down or break it.
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $session = $request->session();

        $visit = $session->get('demo_visit');
        if (! $visit) {
            $visit = Str::random(12);
            $session->put('demo_visit', $visit);
        }

        $zeile = json_encode(array_filter([
            't' => now()->toIso8601String(),
            'v' => $visit,
            'r' => auth()->user()?->role?->name,
            'ip' => $this->url($request),
        ], fn ($wert) => $wert !== null), JSON_UNESCAPED_UNICODE);

        $datei = self::pfad(now()->format('Y-m'));
        File::ensureDirectoryExists(dirname($datei));
        file_put_contents($datei, $zeile."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * The origin depending on the setting: not at all, shortened or in full.
     *
     * Shortening keeps the network part - /24 on IPv4, /48 on IPv6. That is
     * enough for the question which corner the visitors come from and can no
     * longer be tied to one connection. Computed through inet_pton: IPv6
     * cannot be cut reliably as text, because "::"
     * beliebig viele Nullgruppen vertritt.
     */
    private function url(Request $request): ?string
    {
        $modus = config('custom.demo_ip_logging', 'anonym');

        if ($modus === 'aus' || ! ($ip = $request->ip())) {
            return null;
        }

        if ($modus === 'voll') {
            return $ip;
        }

        $roh = @inet_pton($ip);
        if ($roh === false) {
            return null;
        }

        $behalten = strlen($roh) === 4 ? 3 : 6;

        return inet_ntop(substr($roh, 0, $behalten).str_repeat("\0", strlen($roh) - $behalten));
    }

    /** One file per month - keeps the files small and the cleanup simple. */
    public static function pfad(string $monat): string
    {
        return storage_path("app/demo-usage/{$monat}.jsonl");
    }

    public static function verzeichnis(): string
    {
        return storage_path('app/demo-usage');
    }
}
