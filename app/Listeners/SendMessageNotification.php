<?php
// app/Listeners/SendMessageNotification.php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\NotificationService;

class SendMessageNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // Ne pas notifier les messages système
        if ($message->type === 'system') {
            return;
        }

        $this->notificationService->newMessage(
            $message->recipient_id,
            $message
        );
    }
}
