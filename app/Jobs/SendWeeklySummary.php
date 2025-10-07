<?php
// app/Jobs/SendWeeklySummary.php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWeeklySummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        if (!now()->isSunday()) return;

        $activeUsers = User::where('is_active', true)
                          ->where('last_active_at', '>=', now()->subWeek())
                          ->get();

        foreach ($activeUsers as $user) {
            $weeklyStats = $this->calculateWeeklyStats($user);

            if ($weeklyStats['total_activity'] > 0) {
                $notificationService->sendNotification($user->id, 'weekly_summary', [
                    'title' => '📊 Votre résumé de la semaine',
                    'message' => $this->formatWeeklySummary($weeklyStats),
                    'send_push' => false,
                    'send_email' => true,
                    'data' => $weeklyStats
                ]);
            }
        }
    }

    private function calculateWeeklyStats(User $user): array
    {
        $startOfWeek = now()->startOfWeek();

        $stats = [
            'trips_completed' => 0,
            'bookings_made' => 0,
            'earnings' => 0,
            'savings' => 0,
            'km_traveled' => 0,
            'co2_saved' => 0,
        ];

        if ($user->isDriver()) {
            $completedTrips = $user->driverTrips()
                                  ->where('status', 'completed')
                                  ->where('completed_at', '>=', $startOfWeek)
                                  ->with('confirmedBookings')
                                  ->get();

            $stats['trips_completed'] = $completedTrips->count();
            $stats['earnings'] = $completedTrips->sum(function ($trip) {
                return $trip->confirmedBookings->sum('total_amount');
            });
            $stats['km_traveled'] = $completedTrips->sum('total_distance');
        }

        if ($user->isPassenger()) {
            $completedBookings = $user->passengerBookings()
                                     ->where('status', 'completed')
                                     ->where('updated_at', '>=', $startOfWeek)
                                     ->get();

            $stats['bookings_made'] = $completedBookings->count();
            $stats['savings'] = $completedBookings->sum('total_amount') * 0.3;
        }

        $stats['co2_saved'] = ($stats['km_traveled'] * 0.12);
        $stats['total_activity'] = $stats['trips_completed'] + $stats['bookings_made'];

        return $stats;
    }

    private function formatWeeklySummary(array $stats): string
    {
        $messages = [];

        if ($stats['trips_completed'] > 0) {
            $messages[] = "{$stats['trips_completed']} trajet(s) effectué(s)";
        }

        if ($stats['bookings_made'] > 0) {
            $messages[] = "{$stats['bookings_made']} réservation(s) effectuée(s)";
        }

        if ($stats['earnings'] > 0) {
            $messages[] = number_format($stats['earnings'], 0, ',', ' ') . " FCFA gagnés";
        }

        return implode(', ', $messages) . ". Bravo ! 🎉";
    }
}
