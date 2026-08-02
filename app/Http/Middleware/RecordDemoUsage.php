<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Zaehlt Aufrufe auf der Demo-Instanz.
 *
 * Geschrieben wird in eine Datei unter storage/, nicht in die Datenbank: die
 * loescht `demo:reset` stuendlich komplett. Dateien unter storage/ ueberstehen
 * sowohl den Reset als auch das `git reset --hard` beim Deploy.
 *
 * Kein User-Agent, keine aufgerufenen Seiten. Besuche werden ueber einen
 * Zufallswert in der Sitzung unterschieden, der mit der Sitzung verschwindet.
 *
 * Die Herkunft wird nach custom.demo_ip_logging aufgezeichnet, standardmaessig
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

        // Nur echte Seitenaufrufe: POSTs, Livewire-Aktualisierungen und
        // Hintergrundabfragen wuerden die Zahlen sonst aufblaehen.
        if (! $request->isMethod('GET') || $request->ajax() || $request->is('livewire/*')) {
            return $response;
        }

        try {
            $this->record($request);
        } catch (\Throwable $e) {
            // Statistik darf die Demo nie ausbremsen oder kaputtmachen.
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
            'ip' => $this->adresse($request),
        ], fn ($wert) => $wert !== null), JSON_UNESCAPED_UNICODE);

        $datei = self::pfad(now()->format('Y-m'));
        File::ensureDirectoryExists(dirname($datei));
        file_put_contents($datei, $zeile."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Herkunft je nach Einstellung: gar nicht, gekuerzt oder vollstaendig.
     *
     * Gekuerzt wird auf den Netzanteil - /24 bei IPv4, /48 bei IPv6. Das
     * genuegt fuer die Frage, aus welcher Ecke die Besucher kommen, und laesst
     * sich keinem Anschluss mehr zuordnen. Gerechnet wird ueber inet_pton:
     * IPv6 laesst sich als Text nicht zuverlaessig abschneiden, weil "::"
     * beliebig viele Nullgruppen vertritt.
     */
    private function adresse(Request $request): ?string
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

    /** Eine Datei je Monat - haelt die Dateien klein und das Aufraeumen einfach. */
    public static function pfad(string $monat): string
    {
        return storage_path("app/demo-usage/{$monat}.jsonl");
    }

    public static function verzeichnis(): string
    {
        return storage_path('app/demo-usage');
    }
}
