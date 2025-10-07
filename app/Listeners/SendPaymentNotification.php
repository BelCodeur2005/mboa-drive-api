<?php

// app/Listeners/SendPaymentNotification.php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Services\NotificationService;

class SendPaymentNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(PaymentCompleted $event): void
    {
        $payment = $event->payment;

        // Notifier le conducteur qu'il a reçu un paiement
        $this->notificationService->paymentReceived(
            $payment->receiver_id,
            $payment
        );

        // Notifier le passager que son paiement est confirmé
        $this->notificationService->sendNotification(
            $payment->payer_id,
            'payment_confirmed',
            [
                'title' => 'Paiement confirmé',
                'message' => "Votre paiement de " . number_format($payment->amount, 0, ',', ' ') . " FCFA a été confirmé",
                'booking_id' => $payment->booking_id,
                'related_user_id' => $payment->receiver_id,
                'send_push' => true,
                'data' => [
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method_name,
                    'reference' => $payment->transaction_id
                ]
            ]
        );
    }
}
