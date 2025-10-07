<?php
// app/Listeners/SendTripNotification.php

namespace App\Listeners;

use App\Events\TripStarted;
use App\Services\NotificationService;

class SendTripNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(TripStarted $event): void
    {
        $trip = $event->trip;

        // Notifier tous les passagers que le trajet a commencé
        foreach ($trip->confirmedBookings as $booking) {
            $this->notificationService->sendNotification(
                $booking->passenger_id,
                'trip_started',
                [
                    'title' => 'Trajet commencé',
                    'message' => "Le trajet {$trip->departure_city} → {$trip->arrival_city} a commencé",
                    'trip_id' => $trip->id,
                    'related_user_id' => $trip->driver_id,
                    'send_push' => true,
                    'data' => [
                        'driver_name' => $trip->driver->name,
                        'driver_phone' => $trip->driver->phone,
                        'estimated_arrival' => $trip->arrival_date?->toISOString()
                    ]
                ]
            );
        }
    }
}
