<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Zeitplan
|--------------------------------------------------------------------------
|
| Angetrieben von einer einzigen Cron-Zeile auf dem Server:
|
|   * * * * * cd /var/www/dokuvault && php artisan schedule:run >> /dev/null 2>&1
|
| Ein dauerhaft laufender Queue-Worker waere die andere Moeglichkeit, braucht
| aber einen Dienst mit Neustart nach jedem Deploy. Der Minutentakt genuegt
| hier: Ein PDF ist ohnehin keine Sekundensache.
*/

// Die Warteschlange leeren und dann beenden - kein Dauerlaeufer, der nach
// einem Deploy mit altem Code weiterarbeitet. Die Laufzeit bleibt unter einer
// Minute, damit sich zwei Laeufe nicht ins Gehege kommen; withoutOverlapping
// sichert das zusaetzlich ab, denn ein grosses PDF dauert laenger.
//
// --memory=512, weil der Vorgabewert 128 MB betraegt und ein PDF-Auftrag
// darueber liegt: Der Export des Beispielkunden kommt auf 164 MB. Der Worker
// prueft den Verbrauch NACH jedem Auftrag und beendet sich mit Code 12
// (EXIT_MEMORY_LIMIT), wenn er darueber liegt - das erste PDF wird also fertig,
// das zweite blieb liegen und wartete auf den naechsten Minutenlauf. Bei drei
// wartenden Exporten dauerte es damit drei Minuten statt zehn Sekunden.
//
// Die 512 sind kein Bedarf, sondern Luft: Der Auftrag selbst setzt sein
// memory_limit auf 1G, damit auch ein Kunde mit vielen Serverschraenken
// durchlaeuft. Diese Zahl hier entscheidet nur, ab wann der Worker sich fuer
// verbraucht haelt.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1 --memory=512')
    ->everyMinute()
    ->withoutOverlapping(10);

// Fertige PDF wieder loeschen: Sie enthalten alle Zugangsdaten des Kunden und
// haben nach dem Abholen keinen Grund, liegen zu bleiben.
// Stuendlich, nicht einmal nachts: Die Frist steht in Stunden, und eine Frist,
// die nur um 03:30 durchgesetzt wird, ist keine. Ein PDF von 04:00 Uhr lag mit
// 24 Stunden Frist bis zum uebernaechsten Lauf - fast zwei Tage statt einem.
Schedule::command('pdf:aufraeumen')->hourly();

// Das Protokoll nach der eingestellten Frist kuerzen, samt der daran
// haengenden Kennwoerter. Taeglich, nicht stuendlich: Die Frist wird in Tagen
// angegeben, eine Stunde Genauigkeit waere geheuchelte Praezision.
Schedule::command('protokoll:aufraeumen')->dailyAt('03:40');

// API-Statistik (Admin -> Auslastung) nach der eingestellten Frist (Standard
// 90 Tage, Einstellungen -> Fristen) loeschen: Fuer die Frage
// "wann kommt die Last, reicht der Server" genuegt ein Quartal.
Schedule::call(fn () => DB::table('api_request_stats')
    ->where('stunde', '<', now()->subDays(Setting::statistikApiTage()))->delete())
    ->dailyAt('03:50')->name('api-statistik-aufraeumen');

// Kennzahlen des Tages fuer Admin -> Statistik (System, Datenwachstum).
// Nachts, nach dem Backup um 01:30 - dessen Groesse soll schon mitzaehlen.
Schedule::command('statistik:schnappschuss')->dailyAt('02:30');
