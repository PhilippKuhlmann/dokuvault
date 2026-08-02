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
 * Bewusst ohne Personenbezug - keine IP-Adresse, kein User-Agent. Besuche
 * werden ueber einen Zufallswert in der Sitzung unterschieden; der laesst sich
 * keiner Person zuordnen und verschwindet mit der Sitzung.
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

        $zeile = json_encode([
            't' => now()->toIso8601String(),
            'v' => $visit,
            'r' => auth()->user()?->role?->name,
        ], JSON_UNESCAPED_UNICODE);

        $datei = self::pfad(now()->format('Y-m'));
        File::ensureDirectoryExists(dirname($datei));
        file_put_contents($datei, $zeile."\n", FILE_APPEND | LOCK_EX);
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
