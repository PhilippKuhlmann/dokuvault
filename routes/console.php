<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(10);

// Fertige PDF wieder loeschen: Sie enthalten alle Zugangsdaten des Kunden und
// haben nach dem Abholen keinen Grund, liegen zu bleiben.
Schedule::command('pdf:aufraeumen')->dailyAt('03:30');

// Das Protokoll nach der eingestellten Frist kuerzen, samt der daran
// haengenden Kennwoerter. Taeglich, nicht stuendlich: Die Frist wird in Tagen
// angegeben, eine Stunde Genauigkeit waere geheuchelte Praezision.
Schedule::command('protokoll:aufraeumen')->dailyAt('03:40');
