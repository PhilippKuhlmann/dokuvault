<?php

namespace App\Console\Commands;

use App\Http\Middleware\RecordDemoUsage;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Wertet die von RecordDemoUsage geschriebenen Dateien aus.
 *
 * Ein Besuch ist eine Sitzung (Zufallskennung), nicht eine Person: wer nach
 * Ablauf der Sitzung wiederkommt, zaehlt erneut. Genauer geht es ohne
 * Personenbezug nicht, und fuer die Frage "wird die Demo genutzt" reicht es.
 */
class DemoStats extends Command
{
    protected $signature = 'demo:stats
        {--month= : Monat im Format JJJJ-MM, Standard: alle vorhandenen}
        {--days=14 : Wie viele Tage in der Tagesuebersicht}';

    protected $description = 'Nutzung der Demo-Instanz auswerten';

    public function handle(): int
    {
        $zeilen = $this->einlesen();

        if ($zeilen === []) {
            $this->warn('Noch keine Aufrufe aufgezeichnet.');
            $this->line('Erwartet werden Dateien unter '.RecordDemoUsage::verzeichnis());
            $this->line('Aufgezeichnet wird nur, wenn DEMO_MODE=true gesetzt ist.');

            return self::SUCCESS;
        }

        $besuche = [];   // Kennung => ['erste' => Carbon, 'letzte' => Carbon, 'seiten' => int, 'rollen' => []]
        foreach ($zeilen as $z) {
            $t = Carbon::parse($z['t']);
            $v = $z['v'];

            $besuche[$v] ??= ['erste' => $t, 'letzte' => $t, 'seiten' => 0, 'rollen' => []];
            $besuche[$v]['seiten']++;
            $besuche[$v]['letzte'] = $t->max($besuche[$v]['letzte']);
            $besuche[$v]['erste'] = $t->min($besuche[$v]['erste']);
            if ($z['r']) {
                $besuche[$v]['rollen'][$z['r']] = true;
            }
        }

        $this->gesamt($besuche, $zeilen);
        $this->newLine();
        $this->proTag($besuche);
        $this->newLine();
        $this->proStunde($zeilen);
        $this->newLine();
        $this->proRolle($besuche);

        return self::SUCCESS;
    }

    /** @return array<int, array{t:string,v:string,r:?string}> */
    private function einlesen(): array
    {
        $monat = $this->option('month');
        $muster = $monat
            ? RecordDemoUsage::pfad($monat)
            : RecordDemoUsage::verzeichnis().'/*.jsonl';

        $zeilen = [];
        foreach (glob($muster) ?: [] as $datei) {
            foreach (file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
                $daten = json_decode($zeile, true);
                if (is_array($daten) && isset($daten['t'], $daten['v'])) {
                    $zeilen[] = $daten + ['r' => null];
                }
            }
        }

        return $zeilen;
    }

    private function gesamt(array $besuche, array $zeilen): void
    {
        $dauern = array_map(
            fn ($b) => $b['erste']->diffInSeconds($b['letzte']),
            array_values($besuche)
        );
        // Median statt Mittelwert: ein einzelner langer Besuch verzerrt sonst alles.
        sort($dauern);
        $median = $dauern ? $dauern[intdiv(count($dauern), 2)] : 0;

        $erste = collect($besuche)->min(fn ($b) => $b['erste']);
        $letzte = collect($besuche)->max(fn ($b) => $b['letzte']);

        $this->info('Gesamt');
        $this->table(['', ''], [
            ['Besuche', count($besuche)],
            ['Seitenaufrufe', count($zeilen)],
            ['Seiten je Besuch', count($besuche) ? round(count($zeilen) / count($besuche), 1) : 0],
            ['Verweildauer (Median)', $this->dauer($median)],
            ['Erster Aufruf', $erste?->format('d.m.Y H:i')],
            ['Letzter Aufruf', $letzte?->format('d.m.Y H:i')],
        ]);
    }

    private function proTag(array $besuche): void
    {
        $tage = [];
        foreach ($besuche as $b) {
            $tage[$b['erste']->format('Y-m-d')] = ($tage[$b['erste']->format('Y-m-d')] ?? 0) + 1;
        }
        krsort($tage);
        $tage = array_slice($tage, 0, (int) $this->option('days'), true);

        $max = max($tage ?: [1]);
        $this->info('Besuche je Tag');
        foreach (array_reverse($tage, true) as $tag => $anzahl) {
            $balken = str_repeat('█', max(1, (int) round($anzahl / $max * 40)));
            $this->line(sprintf('  %s  %-40s %d', Carbon::parse($tag)->format('d.m.'), $balken, $anzahl));
        }
    }

    private function proStunde(array $zeilen): void
    {
        $stunden = array_fill(0, 24, 0);
        foreach ($zeilen as $z) {
            $stunden[(int) Carbon::parse($z['t'])->format('G')]++;
        }

        $max = max($stunden ?: [1]);
        $this->info('Seitenaufrufe je Tageszeit');
        foreach ($stunden as $stunde => $anzahl) {
            if ($anzahl === 0) {
                continue;
            }
            $balken = str_repeat('█', max(1, (int) round($anzahl / $max * 30)));
            $this->line(sprintf('  %02d Uhr  %-30s %d', $stunde, $balken, $anzahl));
        }
    }

    /** Die Rollennamen aus der Datenbank sind technisch - hier lesbar machen. */
    private const ROLLENNAMEN = [
        'general_full' => 'Kunde (lesen und schreiben)',
        'general_read' => 'Kunde (nur lesen)',
    ];

    private function proRolle(array $besuche): void
    {
        $rollen = [];
        foreach ($besuche as $b) {
            if ($b['rollen'] === []) {
                $rollen['(nur umgeschaut, nicht angemeldet)'] = ($rollen['(nur umgeschaut, nicht angemeldet)'] ?? 0) + 1;

                continue;
            }
            foreach (array_keys($b['rollen']) as $rolle) {
                $name = self::ROLLENNAMEN[$rolle] ?? $rolle;
                $rollen[$name] = ($rollen[$name] ?? 0) + 1;
            }
        }
        arsort($rollen);

        $this->info('Besuche je Rolle');
        $this->table(['Rolle', 'Besuche'], collect($rollen)->map(fn ($n, $r) => [$r, $n])->values()->all());
    }

    private function dauer(int $sekunden): string
    {
        if ($sekunden < 60) {
            return $sekunden.' s';
        }

        return intdiv($sekunden, 60).' min '.($sekunden % 60).' s';
    }
}
