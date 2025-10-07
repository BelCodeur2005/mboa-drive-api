<?php
// app/Listeners/SendRatingNotification.php

namespace App\Listeners;

use App\Events\UserRated;
use App\Services\NotificationService;

class SendRatingNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(UserRated $event): void
    {
        $rating = $event->rating;

        $this->notificationService->sendNotification(
            $rating->rated_id,
            'new_rating',
            [
                'title' => 'Nouvelle évaluation reçue',
                'message' => "{$rating->rater->name} vous a donné {$rating->rating}/5 étoiles" . ($rating->comment ? " avec un commentaire" : ""),
                'booking_id' => $rating->booking_id,
                'related_user_id' => $rating->rater_id,
                'send_push' => true,
                'data' => [
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'rater_name' => $rating->rater->name
                ]
            ]
        );
    }
}
