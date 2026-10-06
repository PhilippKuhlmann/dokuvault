<?php

namespace App\Console;

use App\Support\BackupEinstellungen;
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
        try {
            $aktiv = BackupEinstellungen::aktiv();
            $uhrzeit = BackupEinstellungen::uhrzeit();
        } catch (\Throwable) {
            [$aktiv, $uhrzeit] = [true, '01:30'];
        }
        $aufraeumen = now()->setTimeFromTimeString($uhrzeit)->subMinutes(30)->format('H:i');

        $schedule->command('backup:clean')->dailyAt($aufraeumen)->when(fn () => $aktiv);
        $schedule->command('backup:run')->dailyAt($uhrzeit)->when(fn () => $aktiv);
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
