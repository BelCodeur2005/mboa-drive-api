<?php
// app/Jobs/ProcessPushNotification.php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
class ProcessPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendPushNotification($this->notification);
    }

    /**
     * Gérer l'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Échec envoi push notification {$this->notification->id}: " . $exception->getMessage());

        $this->notification->update([
            'push_response' => [
                'success' => false,
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString()
            ]
        ]);
    }
}
