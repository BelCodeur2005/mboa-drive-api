<?php

// app/Events/NotificationSent.php (pour WebSockets)

namespace App\Events;

use App\Models\Notification;
use App\Http\Resources\NotificationResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->notification->user_id)
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => new NotificationResource($this->notification),
            'unread_count' => $this->notification->user->notifications()
                                                 ->where('is_read', false)
                                                 ->count(),
            'timestamp' => now()->toISOString()
        ];
    }
}
