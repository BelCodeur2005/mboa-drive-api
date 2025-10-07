<?php
// app/Jobs/SendPaymentReminders.php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPaymentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        // Réservations confirmées mais non payées depuis plus de 1h
        $unpaidBookings = Booking::with(['passenger', 'trip'])
                                ->where('status', 'confirmed')
                                ->where('confirmed_at', '<', now()->subHour())
                                ->whereDoesntHave('payments', function ($q) {
                                    $q->where('status', 'completed');
                                })
                                ->get();

        foreach ($unpaidBookings as $booking) {
            $notificationService->sendNotification($booking->passenger_id, 'payment_reminder', [
                'title' => 'Paiement en attente',
                'message' => "N'oubliez pas de payer votre trajet {$booking->trip->departure_city} → {$booking->trip->arrival_city}",
                'booking_id' => $booking->id,
                'send_push' => true,
                'priority' => 'high',
                'data' => [
                    'amount' => $booking->total_amount,
                    'trip_date' => $booking->trip->departure_date->toISOString()
                ]
            ]);
        }
    }
}
