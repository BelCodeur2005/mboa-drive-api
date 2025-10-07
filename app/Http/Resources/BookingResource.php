<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip' => new TripResource($this->whenLoaded('trip')),
            'passenger' => new UserResource($this->whenLoaded('passenger')),
            
            // Détails de la réservation
            'seats_booked' => $this->seats_booked,
            'total_amount' => (float) $this->total_amount,
            'formatted_amount' => number_format($this->total_amount, 0, ',', ' ') . ' FCFA',
            'price_per_seat' => (float) $this->price_per_seat,
            'passenger_message' => $this->passenger_message,
            
            // Points de prise en charge personnalisés
            'pickup' => $this->when($this->pickup_address, [
                'address' => $this->pickup_address,
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ]),
            
            'dropoff' => $this->when($this->dropoff_address, [
                'address' => $this->dropoff_address,
                'latitude' => $this->dropoff_latitude,
                'longitude' => $this->dropoff_longitude,
            ]),
            
            // Statut et timing
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            
            // Suivi du trajet
            'picked_up_at' => $this->picked_up_at?->toISOString(),
            'dropped_off_at' => $this->dropped_off_at?->toISOString(),
            'is_picked_up' => !is_null($this->picked_up_at),
            'is_dropped_off' => !is_null($this->dropped_off_at),
            
            // États calculés
            'can_be_cancelled' => $this->canBeCancelled(),
            'is_active' => in_array($this->status, ['pending', 'confirmed']),
            'is_completed' => $this->status === 'completed',
            
            // Informations de paiement
            'payment_status' => $this->getPaymentStatus(),
            'is_paid' => $this->isPaid(),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'method' => $payment->payment_method_name,
                        'provider' => $payment->provider_name,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at->toISOString(),
                    ];
                });
            }),
            
            // Informations de contact (selon le contexte)
            'contact_info' => $this->getContactInfo($request->user()),
            
            // Évaluations
            'can_be_rated' => $this->canBeRated(),
            'rating_given' => $this->when($this->relationLoaded('ratings'), function () use ($request) {
                return $this->ratings->where('rater_id', $request->user()->id)->first();
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Obtenir le libellé du statut
     */
    private function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'En attente de confirmation',
            'confirmed' => 'Confirmée',
            'cancelled' => 'Annulée',
            'completed' => 'Terminée',
            'no_show' => 'Passager absent',
            default => ucfirst($this->status)
        };
    }

    /**
     * Obtenir le statut de paiement
     */
    private function getPaymentStatus(): string
    {
        if ($this->isPaid()) {
            return 'paid';
        }

        if ($this->payments && $this->payments->where('status', 'pending')->count() > 0) {
            return 'pending';
        }

        return 'unpaid';
    }

    /**
     * Obtenir les informations de contact selon l'utilisateur connecté
     */
    private function getContactInfo($currentUser): ?array
    {
        if (!$currentUser) {
            return null;
        }

        // Si c'est le passager, montrer les infos du conducteur
        if ($this->passenger_id === $currentUser->id && $this->relationLoaded('trip.driver')) {
            return [
                'driver_phone' => $this->trip->driver->phone,
                'driver_name' => $this->trip->driver->name,
                'driver_rating' => $this->trip->driver->average_rating,
            ];
        }

        // Si c'est le conducteur, montrer les infos du passager
        if ($this->trip && $this->trip->driver_id === $currentUser->id && $this->relationLoaded('passenger')) {
            return [
                'passenger_phone' => $this->passenger->phone,
                'passenger_name' => $this->passenger->name,
                'passenger_rating' => $this->passenger->average_rating,
            ];
        }

        return null;
    }

    /**
     * Vérifier si la réservation peut être évaluée
     */
    private function canBeRated(): bool
    {
        return $this->status === 'completed' && !is_null($this->dropped_off_at);
    }
}