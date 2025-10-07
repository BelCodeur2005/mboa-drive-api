<?php
// app/Listeners/SendTripCompletionNotification.php

namespace App\Listeners;

use App\Events\TripCompleted;
use App\Services\NotificationService;

class SendTripCompletionNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(TripCompleted $event): void
    {
        $trip = $event->trip;

        // Notifier le conducteur
        $this->notificationService->sendNotification(
            $trip->driver_id,
            'trip_completed',
            [
                'title' => 'Trajet terminé avec succès',
                'message' => "Votre trajet {$trip->departure_city} → {$trip->arrival_city} est terminé",
                'trip_id' => $trip->id,
                'send_push' => true,
                'data' => [
                    'total_passengers' => $trip->confirmedBookings->count(),
                    'total_earnings' => $trip->confirmedBookings->sum('total_amount')
                ]
            ]
        );

        // Notifier les passagers et demander évaluation
        foreach ($trip->confirmedBookings as $booking) {
            $this->notificationService->sendNotification(
                $booking->passenger_id,
                'trip_completed_rate',
                [
                    'title' => 'Trajet terminé - Évaluez votre conducteur',
                    'message' => "Comment s'est passé votre trajet avec {$trip->driver->name} ?",
                    'trip_id' => $trip->id,
                    'booking_id' => $booking->id,
                    'related_user_id' => $trip->driver_id,
                    'send_push' => true,
                    'data' => [
                        'driver_name' => $trip->driver->name,
                        'action' => 'rate_driver'
                    ]
                ]
            );
        }
    }
}
