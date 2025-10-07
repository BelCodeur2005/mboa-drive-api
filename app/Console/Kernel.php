<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\SendTripReminders;
use App\Jobs\SendPaymentReminders;
use App\Jobs\SendWeeklySummary;
use App\Jobs\CleanupOldNotifications;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Vérifier les rappels de trajets toutes les 5 minutes
        $schedule->job(new SendTripReminders())
                 ->everyFiveMinutes()
                 ->name('send-trip-reminders')
                 ->withoutOverlapping();

        // Rappels de paiement toutes les heures
        $schedule->job(new SendPaymentReminders())
                 ->hourly()
                 ->name('send-payment-reminders')
                 ->withoutOverlapping();

        // Résumé hebdomadaire le dimanche à 9h
        $schedule->job(new SendWeeklySummary())
                 ->weeklyOn(0, '9:00')
                 ->name('send-weekly-summary');

        // Nettoyer les anciennes notifications chaque nuit à 2h
        $schedule->job(new CleanupOldNotifications())
                 ->dailyAt('02:00')
                 ->name('cleanup-old-notifications');

        // Nettoyer les sessions expirées
        $schedule->command('auth:clear-resets')
                 ->everyFifteenMinutes();

        // Sauvegarder la base de données (si configuré)
        if (config('database.backup.enabled', false)) {
            $schedule->command('backup:run')
                     ->daily()
                     ->at('01:00');
        }

        // Calculer les statistiques utilisateurs quotidiennes
        $schedule->call(function () {
            \App\Models\User::chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->updateAverageRating();
                    $user->markAsActive();
                }
            });
        })->dailyAt('03:00')->name('update-user-stats');

        // Vérifier les trajets en retard
        $schedule->call(function () {
            $lateTrips = \App\Models\Trip::where('status', 'scheduled')
                                        ->where('departure_date', '<', now()->subHours(2))
                                        ->get();

            foreach ($lateTrips as $trip) {
                $trip->update(['status' => 'cancelled']);

                // Notifier les passagers
                foreach ($trip->confirmedBookings as $booking) {
                    app(\App\Services\NotificationService::class)->sendNotification(
                        $booking->passenger_id,
                        'trip_auto_cancelled',
                        [
                            'title' => 'Trajet annulé automatiquement',
                            'message' => 'Le trajet semble avoir été oublié par le conducteur',
                            'trip_id' => $trip->id,
                            'send_push' => true,
                            'priority' => 'high'
                        ]
                    );
                }
            }
        })->everyThirtyMinutes()->name('check-late-trips');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
