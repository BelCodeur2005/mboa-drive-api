<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'year' => $this->year,
            'license_plate' => $this->license_plate,
            'fuel_type' => $this->fuel_type,
            'seats' => $this->seats,
            'available_seats' => $this->available_seats,
            'air_conditioning' => $this->air_conditioning,
            'description' => $this->description,
            'is_verified' => $this->is_verified,
            'is_active' => $this->is_active,
            'full_name' => $this->full_name,
            'photos' => $this->photo_urls,
            'main_photo' => $this->main_photo_url,
            'created_at' => $this->created_at?->toISOString(),

            // Informations du propriétaire (si chargé et autorisé)
            'owner' => new UserResource($this->whenLoaded('owner')),

            // Statistiques du véhicule
            'stats' => $this->when($this->relationLoaded('trips'), [
                'total_trips' => $this->trips->count(),
                'completed_trips' => $this->trips->where('status', 'completed')->count(),
                'total_kilometers' => $this->trips->sum('total_distance'),
                'average_rating' => $this->trips->avg('driver.average_rating'),
            ]),

            // Badges du véhicule
            'badges' => $this->getVehicleBadges(),
        ];
    }

    /**
     * Obtenir les badges du véhicule
     */
    private function getVehicleBadges(): array
    {
        $badges = [];

        // Badge véhicule vérifié
        if ($this->is_verified) {
            $badges[] = [
                'type' => 'verified',
                'label' => 'Véhicule vérifié',
                'icon' => 'shield-check',
                'color' => 'green'
            ];
        }

        // Badge climatisation
        if ($this->air_conditioning) {
            $badges[] = [
                'type' => 'air_conditioning',
                'label' => 'Climatisation',
                'icon' => 'snowflake',
                'color' => 'blue'
            ];
        }

        // Badge véhicule récent
        if ($this->year >= now()->year - 5) {
            $badges[] = [
                'type' => 'recent_vehicle',
                'label' => 'Véhicule récent',
                'icon' => 'calendar-check',
                'color' => 'purple'
            ];
        }

        // Badge véhicule spacieux
        if ($this->seats >= 7) {
            $badges[] = [
                'type' => 'spacious',
                'label' => 'Véhicule spacieux',
                'icon' => 'users',
                'color' => 'orange'
            ];
        }

        // Badge éco-friendly
        if (in_array($this->fuel_type, ['hybrid', 'electric'])) {
            $badges[] = [
                'type' => 'eco_friendly',
                'label' => 'Écologique',
                'icon' => 'leaf',
                'color' => 'green'
            ];
        }

        return $badges;
    }
}
