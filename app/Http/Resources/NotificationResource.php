<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->is_read,
            'priority' => $this->priority,
            'created_at' => $this->created_at?->toISOString(),
            'created_ago' => $this->created_at?->diffForHumans(),
            'read_at' => $this->read_at?->toISOString(),

            // Informations visuelles
            'icon' => $this->icon,
            'color' => $this->color,
            'action_url' => $this->action_url,

            // Utilisateur lié (si applicable)
            'related_user' => $this->when($this->related_user_id && $this->relationLoaded('relatedUser'), function () {
                return [
                    'id' => $this->relatedUser->id,
                    'name' => $this->relatedUser->name,
                    'profile_photo_url' => $this->relatedUser->profile_photo_url,
                ];
            }),

            // Réservation liée (si applicable)
            'booking' => $this->when($this->booking_id && $this->relationLoaded('booking'), function () {
                return [
                    'id' => $this->booking->id,
                    'trip_id' => $this->booking->trip_id,
                    'seats_booked' => $this->booking->seats_booked,
                    'total_amount' => $this->booking->total_amount,
                ];
            }),

            // Trajet lié (si applicable)
            'trip' => $this->when($this->trip_id && $this->relationLoaded('trip'), function () {
                return [
                    'id' => $this->trip->id,
                    'departure_city' => $this->trip->departure_city,
                    'arrival_city' => $this->trip->arrival_city,
                    'departure_date' => $this->trip->departure_date?->toISOString(),
                    'price_per_seat' => $this->trip->price_per_seat,
                ];
            }),

            // Badges visuels
            'badges' => $this->getNotificationBadges(),

            // État de lecture visuel
            'read_status' => [
                'is_new' => !$this->is_read && $this->created_at->gt(now()->subMinutes(5)),
                'is_recent' => $this->created_at->gt(now()->subHours(24)),
                'is_urgent' => $this->priority === 'high',
                'show_badge' => !$this->is_read,
            ],

            // Informations pour l'affichage
            'display' => [
                'title_formatted' => $this->getFormattedTitle(),
                'message_preview' => $this->getMessagePreview(),
                'time_display' => $this->getTimeDisplay(),
                'category' => $this->getNotificationCategory(),
            ],

            // Actions disponibles
            'actions' => $this->getAvailableActions($request->user()),

            // Métadonnées pour le push/WebSocket
            'push_data' => [
                'sound' => $this->getSoundForType(),
                'vibration' => $this->getVibrationPattern(),
                'channel_id' => 'mboa_drive_notifications',
            ],
        ];
    }

    /**
     * Obtenir les badges de la notification
     */
    private function getNotificationBadges(): array
    {
        $badges = [];

        // Badge selon le statut
        if (!$this->is_read) {
            $badges[] = [
                'type' => 'unread',
                'label' => 'Non lu',
                'color' => 'blue',
                'icon' => 'circle',
            ];
        }

        // Badge selon la priorité
        if ($this->priority === 'high') {
            $badges[] = [
                'type' => 'priority',
                'label' => 'Important',
                'color' => 'red',
                'icon' => 'exclamation',
            ];
        }

        // Badge selon le type
        switch ($this->type) {
            case 'booking_confirmed':
                $badges[] = [
                    'type' => 'booking',
                    'label' => 'Réservation',
                    'color' => 'green',
                    'icon' => 'check-circle',
                ];
                break;

            case 'payment_received':
                $badges[] = [
                    'type' => 'payment',
                    'label' => 'Paiement',
                    'color' => 'green',
                    'icon' => 'dollar-sign',
                ];
                break;

            case 'trip_starting_soon':
                $badges[] = [
                    'type' => 'urgent',
                    'label' => 'Urgent',
                    'color' => 'orange',
                    'icon' => 'clock',
                ];
                break;

            case 'new_message':
                $badges[] = [
                    'type' => 'message',
                    'label' => 'Message',
                    'color' => 'blue',
                    'icon' => 'message-circle',
                ];
                break;

            case 'new_rating':
                $badges[] = [
                    'type' => 'rating',
                    'label' => 'Évaluation',
                    'color' => 'yellow',
                    'icon' => 'star',
                ];
                break;
        }

        // Badge si récent (moins de 5 minutes)
        if ($this->created_at->gt(now()->subMinutes(5))) {
            $badges[] = [
                'type' => 'new',
                'label' => 'Nouveau',
                'color' => 'purple',
                'icon' => 'sparkles',
            ];
        }

        return $badges;
    }

    /**
     * Obtenir le titre formaté avec émojis
     */
    private function getFormattedTitle(): string
    {
        $emoji = match($this->type) {
            'booking_confirmed' => '✅',
            'booking_cancelled' => '❌',
            'trip_starting_soon' => '⏰',
            'payment_received' => '💰',
            'payment_failed' => '⚠️',
            'new_message' => '💬',
            'new_rating' => '⭐',
            'trip_completed' => '🏁',
            'user_verified' => '✅',
            default => '🔔'
        };

        return $emoji . ' ' . $this->title;
    }

    /**
     * Obtenir un aperçu du message (tronqué)
     */
    private function getMessagePreview(int $length = 100): string
    {
        if (strlen($this->message) <= $length) {
            return $this->message;
        }

        return substr($this->message, 0, $length) . '...';
    }

    /**
     * Obtenir l'affichage du temps
     */
    private function getTimeDisplay(): array
    {
        if (!$this->created_at) {
            return ['short' => '', 'full' => ''];
        }

        $now = now();
        $notificationDate = $this->created_at;

        // Si c'est très récent (moins de 1 minute)
        if ($notificationDate->gt($now->subMinute())) {
            return [
                'short' => 'maintenant',
                'full' => 'À l\'instant',
                'is_immediate' => true
            ];
        }

        // Si c'est aujourd'hui
        if ($notificationDate->isToday()) {
            return [
                'short' => $notificationDate->format('H:i'),
                'full' => 'Aujourd\'hui à ' . $notificationDate->format('H:i'),
                'is_today' => true
            ];
        }

        // Si c'est hier
        if ($notificationDate->isYesterday()) {
            return [
                'short' => 'Hier',
                'full' => 'Hier à ' . $notificationDate->format('H:i'),
                'is_yesterday' => true
            ];
        }

        // Si c'est cette semaine
        if ($notificationDate->gt($now->startOfWeek())) {
            $dayName = $notificationDate->locale('fr')->dayName;
            return [
                'short' => ucfirst($dayName),
                'full' => ucfirst($dayName) . ' à ' . $notificationDate->format('H:i'),
                'is_this_week' => true
            ];
        }

        // Si c'est cette année
        if ($notificationDate->year === $now->year) {
            return [
                'short' => $notificationDate->format('d/m'),
                'full' => $notificationDate->format('d/m à H:i'),
                'is_this_year' => true
            ];
        }

        // Sinon
        return [
            'short' => $notificationDate->format('d/m/Y'),
            'full' => $notificationDate->format('d/m/Y à H:i'),
            'is_old' => true
        ];
    }

    /**
     * Obtenir la catégorie de la notification
     */
    private function getNotificationCategory(): string
    {
        return match($this->type) {
            'booking_confirmed', 'booking_cancelled', 'booking_received' => 'booking',
            'trip_starting_soon', 'trip_started', 'trip_completed' => 'trip',
            'payment_received', 'payment_failed', 'payment_reminder' => 'payment',
            'new_message' => 'message',
            'new_rating' => 'rating',
            'user_verified', 'weekly_summary' => 'account',
            default => 'general'
        };
    }

    /**
     * Obtenir les actions disponibles
     */
    private function getAvailableActions($currentUser): array
    {
        if (!$currentUser || $this->user_id !== $currentUser->id) {
            return [];
        }

        $actions = [];

        // Action "Marquer comme lu" si non lu
        if (!$this->is_read) {
            $actions[] = [
                'type' => 'mark_read',
                'label' => 'Marquer comme lu',
                'icon' => 'check',
                'endpoint' => "/api/v1/notifications/{$this->id}/read"
            ];
        }

        // Actions selon le type
        switch ($this->type) {
            case 'booking_confirmed':
            case 'booking_cancelled':
                if ($this->booking_id) {
                    $actions[] = [
                        'type' => 'view_booking',
                        'label' => 'Voir la réservation',
                        'icon' => 'eye',
                        'endpoint' => "/api/v1/bookings/{$this->booking_id}"
                    ];
                }
                break;

            case 'trip_starting_soon':
                if ($this->trip_id) {
                    $actions[] = [
                        'type' => 'view_trip',
                        'label' => 'Voir le trajet',
                        'icon' => 'map',
                        'endpoint' => "/api/v1/trips/{$this->trip_id}"
                    ];
                }
                break;

            case 'new_message':
                if ($this->related_user_id) {
                    $actions[] = [
                        'type' => 'reply_message',
                        'label' => 'Répondre',
                        'icon' => 'reply',
                        'endpoint' => "/api/v1/messages/conversation/{$this->related_user_id}"
                    ];
                }
                break;

            case 'payment_received':
                $actions[] = [
                    'type' => 'view_payments',
                    'label' => 'Voir les paiements',
                    'icon' => 'dollar-sign',
                    'endpoint' => '/api/v1/payments/history'
                ];
                break;

            case 'new_rating':
                $actions[] = [
                    'type' => 'view_ratings',
                    'label' => 'Voir les évaluations',
                    'icon' => 'star',
                    'endpoint' => '/api/v1/ratings/received'
                ];
                break;
        }

        return $actions;
    }

    /**
     * Obtenir le son pour ce type de notification
     */
    private function getSoundForType(): string
    {
        return match($this->type) {
            'booking_confirmed', 'payment_received' => 'success',
            'booking_cancelled', 'payment_failed' => 'error',
            'trip_starting_soon' => 'urgent',
            'new_message' => 'message',
            'new_rating' => 'star',
            default => 'default'
        };
    }

    /**
     * Obtenir le pattern de vibration
     */
    private function getVibrationPattern(): array
    {
        return match($this->priority) {
            'high' => [0, 500, 200, 500, 200, 500],
            'normal' => [0, 300, 100, 300],
            default => [0, 200]
        };
    }
}
