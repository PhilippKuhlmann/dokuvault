<?php

namespace App\Console;

use App\Support\BackupEinstellungen;
use App\Support\Zeit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Time and on/off from Admin -> Einstellungen -> Backup. The cleanup
        // runs half an hour before, so the new backup does not count yet.
        // A broken database must not stop the scheduler: then the defaults.
        //
        // The time is meant in the time zone set under Admin -> Allgemein -
        // "01:30" in the form is 01:30 local, not UTC (that was 03:30 in
        // Germany). Stored and logged stays UTC, see App\Support\Zeit.
        try {
            $aktiv = BackupEinstellungen::aktiv();
            $uhrzeit = BackupEinstellungen::uhrzeit();
            $zone = Zeit::zone();
        } catch (\Throwable) {
            [$aktiv, $uhrzeit, $zone] = [true, '01:30', config('app.timezone')];
        }
        $aufraeumen = now()->setTimeFromTimeString($uhrzeit)->subMinutes(30)->format('H:i');

        $schedule->command('backup:clean')->dailyAt($aufraeumen)->timezone($zone)->when(fn () => $aktiv);
        $schedule->command('backup:run')->dailyAt($uhrzeit)->timezone($zone)->when(fn () => $aktiv);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
