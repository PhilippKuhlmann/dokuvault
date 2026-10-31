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

// Drain the queue and then stop - no long runner that keeps working with old
// code after a deploy. The runtime stays under a minute so two runs do not
// get in each other's way; withoutOverlapping secures that on top, because a
// large PDF takes longer.
//
// --memory=512, because the default is 128 MB and a PDF job sits above it:
// the export of the sample customer comes to 164 MB. The worker checks its
// usage AFTER every job and stops with code 12 (EXIT_MEMORY_LIMIT) if it is
// above - so the first PDF finishes and the second one stayed put, waiting
// for the next minute's run. With three exports waiting that took three
// minutes instead of ten seconds.
//
// The 512 are not a requirement but headroom: the job itself sets its
// memory_limit to 1G so a customer with many racks gets through as well.
// This number here only decides when the worker considers itself used up.
//
// The demo rebuilds its database at every full hour (demo:reset, its own
// cron line). A job started in the same minute found its tables gone and
// failed - twice an hour in the log. On the demo, jobs that would run at a
// full hour move to minute 20.
$demo = (bool) config('app.demo');

Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1 --memory=512')
    ->everyMinute()
    ->withoutOverlapping(10);

// Delete finished PDFs again: they hold every password of the customer and
// have no reason to stay once collected. Hourly, not once a night: the
// retention is set in hours, and one enforced only at 03:30 is none - a PDF
// from 04:00 with 24 hours retention stayed until the run after next.
Schedule::command('pdf:purge')->hourlyAt($demo ? 20 : 0);

// Trim the activity log after the configured deadline, together with the
// passwords hanging on it. Daily, not hourly: the deadline is given in days,
// and an hour's precision would be feigned accuracy.
Schedule::command('activity:prune')->dailyAt('03:40');

// API statistics (Admin -> Auslastung) deleted after the configured deadline
// (90 days by default, Einstellungen -> Fristen): for the question "when does
// the load come, is the server enough" a quarter is plenty.
Schedule::call(fn () => DB::table('api_request_stats')
    ->where('stunde', '<', now()->subDays(Setting::statistikApiTage()))->delete())
    ->dailyAt('03:50')->name('api-stats-cleanup');

// Domains and certificates checked against the internet (RDAP, DNS, TLS) -
// before the expiry mail, so it already reports the dates found tonight.
Schedule::command('domains:check')->dailyAt($demo ? '05:20' : '05:00')->withoutOverlapping(60);

// Expiry mails (Admin -> Einstellungen -> Benachrichtigungen). In the morning, so
// the list is there when the day starts; the scheduler runs in UTC.
Schedule::command('expiry:notify')->dailyAt('05:30');

// The day's figures for Admin -> Statistik (System, Datenwachstum). At night,
// after the backup at 01:30 - its size should already count.
Schedule::command('stats:snapshot')->dailyAt('02:30');
