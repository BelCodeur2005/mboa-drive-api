<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver' => new UserResource($this->whenLoaded('driver')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),

            // Itinéraire
            'departure' => [
                'city' => $this->departure_city,
                'address' => $this->departure_address,
                'latitude' => $this->departure_latitude,
                'longitude' => $this->departure_longitude,
                'date' => $this->departure_date?->toISOString(),
                'formatted_date' => $this->departure_date?->format('d/m/Y H:i'),
                'formatted_time' => $this->departure_date?->format('H:i'),
            ],
            'arrival' => [
                'city' => $this->arrival_city,
                'address' => $this->arrival_address,
                'latitude' => $this->arrival_latitude,
                'longitude' => $this->arrival_longitude,
                'date' => $this->arrival_date?->toISOString(),
                'formatted_date' => $this->arrival_date?->format('d/m/Y H:i'),
                'formatted_time' => $this->arrival_date?->format('H:i'),
            ],

            // Points d'arrêt
            'stops' => $this->whenLoaded('stops', function () {
                return $this->stops->map(function ($stop) {
                    return [
                        'id' => $stop->id,
                        'city' => $stop->city,
                        'address' => $stop->address,
                        'latitude' => $stop->latitude,
                        'longitude' => $stop->longitude,
                        'order' => $stop->order,
                    ];
                });
            }),

            // Capacité et disponibilité
            'seats' => [
                'total' => $this->total_seats,
                'available' => $this->available_seats,
                'booked' => $this->booked_seats,
                'remaining' => $this->available_seats - $this->booked_seats,
            ],

            // Prix et distance
            'price_per_seat' => (float) $this->price_per_seat,
            'total_price' => (float) $this->total_price,
            'formatted_price' => number_format($this->price_per_seat, 0, ',', ' ') . ' FCFA',
            'total_distance' => $this->total_distance,
            'estimated_duration' => $this->estimated_duration,
            'formatted_duration' => $this->formatted_duration,

            // Préférences et règles
            'preferences' => [
                'instant_booking' => $this->instant_booking,
                'smoking_allowed' => $this->smoking_allowed,
                'pets_allowed' => $this->pets_allowed,
                'max_2_in_back' => $this->max_2_in_back,
            ],

            // Informations supplémentaires
            'additional_info' => $this->additional_info,
            'status' => $this->status,
            'is_recurring' => $this->is_recurring,
            'recurring_days' => $this->recurring_days,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            // États calculés
            'is_full' => $this->isFull(),
            'can_be_booked' => $this->canBeBookedInstantly(),
            'is_upcoming' => $this->departure_date && $this->departure_date->gt(now()),
            'is_today' => $this->departure_date && $this->departure_date->isToday(),
            'is_past' => $this->departure_date && $this->departure_date->lt(now()),

            // Réservations (pour le conducteur ou les détails)
            'bookings' => $this->whenLoaded('confirmedBookings', function () {
                return $this->confirmedBookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'passenger' => [
                            'id' => $booking->passenger->id,
                            'name' => $booking->passenger->name,
                            'profile_photo_url' => $booking->passenger->profile_photo_url,
                            'average_rating' => $booking->passenger->average_rating,
                            'phone' => $this->when(
                                request()->user() && request()->user()->id === $this->driver_id,
                                $booking->passenger->phone
                            ),
                        ],
                        'seats_booked' => $booking->seats_booked,
                        'total_amount' => $booking->total_amount,
                        'status' => $booking->status,
                        'pickup_address' => $booking->pickup_address,
                        'dropoff_address' => $booking->dropoff_address,
                        'picked_up_at' => $booking->picked_up_at?->toISOString(),
                        'dropped_off_at' => $booking->dropped_off_at?->toISOString(),
                    ];
                });
            }),

            // Statistiques pour le conducteur
            'stats' => $this->when(request()->user() && request()->user()->id === $this->driver_id, [
                'total_earnings' => $this->confirmedBookings->sum('total_amount'),
                'pending_bookings' => $this->bookings()->where('status', 'pending')->count(),
                'confirmed_bookings' => $this->bookings()->where('status', 'confirmed')->count(),
            ]),

            // Informations de contact (uniquement pour les passagers ayant réservé)
            'contact_info' => $this->when(
                request()->user() && $this->hasUserBooked(request()->user()->id),
                [
                    'driver_phone' => $this->driver->phone,
                    'emergency_contact' => $this->driver->emergency_contacts[0] ?? null,
                ]
            ),
        ];
    }

    /**
     * Vérifier si un utilisateur a réservé ce trajet
     */
    private function hasUserBooked(int $userId): bool
    {
        return $this->bookings()
                   ->where('passenger_id', $userId)
                   ->whereIn('status', ['confirmed', 'pending'])
                   ->exists();
    }
}
