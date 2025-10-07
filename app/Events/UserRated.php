<?php
// app/Events/UserRated.php

namespace App\Events;

use App\Models\Rating;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Rating $rating
    ) {}
}
