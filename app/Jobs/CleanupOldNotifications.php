<?php

// app/Jobs/CleanupOldNotifications.php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
class CleanupOldNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Supprimer les notifications lues de plus de 30 jours
        $deletedCount = Notification::where('created_at', '<', now()->subDays(30))
                                   ->where('is_read', true)
                                   ->delete();

        Log::info("Nettoyage notifications: {$deletedCount} notifications supprimées");

        // Supprimer les notifications non lues de plus de 90 jours (très anciennes)
        $veryOldCount = Notification::where('created_at', '<', now()->subDays(90))
                                   ->delete();

        if ($veryOldCount > 0) {
            Log::info("Nettoyage notifications très anciennes: {$veryOldCount} notifications supprimées");
        }
    }
}
