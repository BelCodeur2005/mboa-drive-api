<?php
// app/Listeners/SendBookingCancellationNotification.php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Services\NotificationService;

class SendBookingCancellationNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;
        $reason = $event->reason;

        // Notifier le conducteur si c'est le passager qui annule
        if ($booking->passenger_id !== auth()->id()) {
            $this->notificationService->sendNotification(
                $booking->trip->driver_id,
                'booking_cancelled',
                [
                    'title' => 'Réservation annulée',
                    'message' => "{$booking->passenger->name} a annulé sa réservation" . ($reason ? " : $reason" : ""),
                    'booking_id' => $booking->id,
                    'related_user_id' => $booking->passenger_id,
                    'send_push' => true,
                    'priority' => 'high'
                ]
            );
        }

        // Notifier le passager si c'est le conducteur qui annule
        if ($booking->trip->driver_id !== auth()->id()) {
            $this->notificationService->sendNotification(
                $booking->passenger_id,
                'booking_cancelled_by_driver',
                [
                    'title' => 'Réservation annulée par le conducteur',
                    'message' => "Votre réservation pour {$booking->trip->departure_city} → {$booking->trip->arrival_city} a été annulée" . ($reason ? " : $reason" : ""),
                    'booking_id' => $booking->id,
                    'related_user_id' => $booking->trip->driver_id,
                    'send_push' => true,
                    'send_sms' => true,
                    'priority' => 'high'
                ]
            );
        }
    }
}
