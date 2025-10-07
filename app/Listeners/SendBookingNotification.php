<?php
// app/Listeners/SendBookingNotification.php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Services\NotificationService;

class SendBookingNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        // Notifier le passager
        $this->notificationService->bookingConfirmed(
            $booking->passenger_id,
            $booking
        );

        // Notifier le conducteur
        $this->notificationService->sendNotification(
            $booking->trip->driver_id,
            'booking_received',
            [
                'title' => 'Nouvelle réservation',
                'message' => "{$booking->passenger->name} a réservé {$booking->seats_booked} place(s)",
                'booking_id' => $booking->id,
                'related_user_id' => $booking->passenger_id,
                'send_push' => true
            ]
        );
    }
}
