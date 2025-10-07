<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'rating' => $this->rating,
            'rating_text' => $this->rating_text,
            'comment' => $this->comment,
            'type' => $this->type,
            'created_at' => $this->created_at?->toISOString(),
            'created_ago' => $this->created_at?->diffForHumans(),

            // Utilisateur qui a donné l'évaluation
            'rater' => $this->when($this->relationLoaded('rater'), function () {
                return [
                    'id' => $this->rater->id,
                    'name' => $this->rater->name,
                    'profile_photo_url' => $this->rater->profile_photo_url,
                ];
            }),

            // Utilisateur qui a reçu l'évaluation
            'rated' => $this->when($this->relationLoaded('rated'), function () {
                return [
                    'id' => $this->rated->id,
                    'name' => $this->rated->name,
                    'profile_photo_url' => $this->rated->profile_photo_url,
                    'average_rating' => $this->rated->average_rating,
                ];
            }),

            // Informations sur la réservation et le trajet
            'trip_info' => $this->when($this->relationLoaded('booking.trip'), function () {
                return [
                    'departure_city' => $this->booking->trip->departure_city,
                    'arrival_city' => $this->booking->trip->arrival_city,
                    'departure_date' => $this->booking->trip->departure_date->format('d/m/Y'),
                    'price_per_seat' => $this->booking->trip->price_per_seat,
                ];
            }),

            // Badges/indicateurs
            'badges' => $this->getRatingBadges(),

            // Indicateurs visuels
            'is_positive' => $this->isPositive(),
            'is_negative' => $this->isNegative(),
            'stars' => $this->getStarsArray(),
        ];
    }

    /**
     * Obtenir les badges de l'évaluation
     */
    private function getRatingBadges(): array
    {
        $badges = [];

        if ($this->rating === 5) {
            $badges[] = [
                'type' => 'excellent',
                'label' => 'Excellent',
                'color' => 'gold',
                'icon' => 'star-fill'
            ];
        }

        if ($this->rating === 1) {
            $badges[] = [
                'type' => 'poor',
                'label' => 'Mauvais',
                'color' => 'red',
                'icon' => 'star'
            ];
        }

        if (!empty($this->comment) && strlen($this->comment) > 100) {
            $badges[] = [
                'type' => 'detailed',
                'label' => 'Commentaire détaillé',
                'color' => 'blue',
                'icon' => 'message-square'
            ];
        }

        if ($this->type === 'driver_to_passenger') {
            $badges[] = [
                'type' => 'from_driver',
                'label' => 'Par le conducteur',
                'color' => 'green',
                'icon' => 'steering-wheel'
            ];
        } else {
            $badges[] = [
                'type' => 'from_passenger',
                'label' => 'Par le passager',
                'color' => 'purple',
                'icon' => 'user'
            ];
        }

        return $badges;
    }

    /**
     * Obtenir un tableau des étoiles pour l'affichage
     */
    private function getStarsArray(): array
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[] = [
                'position' => $i,
                'filled' => $i <= $this->rating,
                'half' => false // Pourrait être utilisé pour les demi-étoiles à l'avenir
            ];
        }
        return $stars;
    }
}
