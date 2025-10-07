<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_photo_url' => $this->profile_photo_url,
            'user_type' => $this->user_type,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'bio' => $this->bio,
            'city' => $this->city,
            'is_verified' => $this->is_verified,
            'phone_verified' => $this->phone_verified,
            'is_active' => $this->is_active,
            'average_rating' => (float) $this->average_rating,
            'total_ratings' => $this->total_ratings,
            'emergency_contacts' => $this->emergency_contacts,
            'preferences' => $this->preferences,
            'created_at' => $this->created_at?->toISOString(),
            'last_active_at' => $this->last_active_at?->toISOString(),

            // Relations chargées conditionnellement
            'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
            'primary_vehicle' => new VehicleResource($this->whenLoaded('primaryVehicle')),

            // Statistiques du conducteur
            'driver_stats' => $this->when($this->isDriver(), [
                'total_trips' => $this->whenCounted('driverTrips'),
                'completed_trips' => $this->driverTrips()->where('status', 'completed')->count(),
                'cancelled_trips' => $this->driverTrips()->where('status', 'cancelled')->count(),
            ]),

            // Statistiques du passager
            'passenger_stats' => $this->when($this->isPassenger(), [
                'total_bookings' => $this->whenCounted('passengerBookings'),
                'completed_bookings' => $this->passengerBookings()->where('status', 'completed')->count(),
                'cancelled_bookings' => $this->passengerBookings()->where('status', 'cancelled')->count(),
            ]),

            // Badges/statuts
            'badges' => $this->getBadges(),
        ];
    }

    /**
     * Obtenir les badges de l'utilisateur
     */
    private function getBadges(): array
    {
        $badges = [];

        // Badge utilisateur vérifié
        if ($this->is_verified) {
            $badges[] = [
                'type' => 'verified',
                'label' => 'Vérifié',
                'icon' => 'verified',
                'color' => 'blue'
            ];
        }

        // Badge téléphone vérifié
        if ($this->phone_verified) {
            $badges[] = [
                'type' => 'phone_verified',
                'label' => 'Téléphone vérifié',
                'icon' => 'phone-check',
                'color' => 'green'
            ];
        }

        // Badge nouveau membre
        if ($this->created_at && $this->created_at->gt(now()->subDays(30))) {
            $badges[] = [
                'type' => 'new_member',
                'label' => 'Nouveau membre',
                'icon' => 'star',
                'color' => 'yellow'
            ];
        }

        // Badge conducteur expérimenté
        if ($this->isDriver()) {
            $completedTrips = $this->driverTrips()->where('status', 'completed')->count();
            if ($completedTrips >= 10) {
                $badges[] = [
                    'type' => 'experienced_driver',
                    'label' => 'Conducteur expérimenté',
                    'icon' => 'steering-wheel',
                    'color' => 'orange'
                ];
            }
        }

        // Badge passager régulier
        if ($this->isPassenger()) {
            $completedBookings = $this->passengerBookings()->where('status', 'completed')->count();
            if ($completedBookings >= 10) {
                $badges[] = [
                    'type' => 'regular_passenger',
                    'label' => 'Passager régulier',
                    'icon' => 'user-check',
                    'color' => 'purple'
                ];
            }
        }

        // Badge excellent rating
        if ($this->average_rating >= 4.8 && $this->total_ratings >= 5) {
            $badges[] = [
                'type' => 'excellent_rating',
                'label' => 'Excellentes évaluations',
                'icon' => 'star-fill',
                'color' => 'gold'
            ];
        }

        return $badges;
    }
}
