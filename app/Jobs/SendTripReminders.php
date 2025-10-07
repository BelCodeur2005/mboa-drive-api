<?php
// app/Jobs/SendTripReminders.php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTripReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        $now = now();

        // Rappels 30 minutes avant le départ
        $upcomingTrips = Trip::with(['driver', 'confirmedBookings.passenger'])
                           ->where('departure_date', '>', $now)
                           ->where('departure_date', '<=', $now->addMinutes(30))
                           ->where('status', 'scheduled')
                           ->get();

        foreach ($upcomingTrips as $trip) {
            $notificationService->tripStartingSoon($trip->driver_id, $trip);
        }

        // Rappels 2 heures avant
        $trips2h = Trip::with(['driver', 'confirmedBookings.passenger'])
                      ->where('departure_date', '>', $now->copy()->addHours(2))
                      ->where('departure_date', '<=', $now->copy()->addHours(2)->addMinutes(5))
                      ->where('status', 'scheduled')
                      ->get();

        foreach ($trips2h as $trip) {
            $notificationService->sendNotification($trip->driver_id, 'trip_reminder_2h', [
                'title' => 'Trajet dans 2 heures',
                'message' => "Préparez-vous pour votre trajet {$trip->departure_city} → {$trip->arrival_city}",
                'trip_id' => $trip->id,
                'send_push' => true,
                'priority' => 'normal'
            ]);
        }
    }
}
