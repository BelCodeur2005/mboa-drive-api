<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'passenger_id',
        'seats_booked',
        'total_amount',
        'passenger_message',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'status',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'picked_up_at',
        'dropped_off_at'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'dropoff_latitude' => 'decimal:8',
        'dropoff_longitude' => 'decimal:8',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'dropped_off_at' => 'datetime'
    ];

    // Relations

    /**
     * Trajet associé
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Passager qui a effectué la réservation
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    /**
     * Paiements associés à cette réservation
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes

    /**
     * Réservations confirmées
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Réservations en attente
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Réservations d'un passager
     */
    public function scopeByPassenger(Builder $query, int $passengerId): Builder
    {
        return $query->where('passenger_id', $passengerId);
    }

    // Méthodes utilitaires

    /**
     * Confirme la réservation
     */
    public function confirm(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now()
        ]);

        // Mettre à jour les places disponibles du trajet
        $this->trip->decrement('available_seats', $this->seats_booked);
    }

    /**
     * Annule la réservation
     */
    public function cancel(string $reason = null): void
    {
        // Si la réservation était confirmée, remettre les places disponibles
        if ($this->status === 'confirmed') {
            $this->trip->increment('available_seats', $this->seats_booked);
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);
    }

    /**
     * Marque le passager comme pris en charge
     */
    public function markAsPickedUp(): void
    {
        $this->update([
            'picked_up_at' => now()
        ]);
    }

    /**
     * Marque le passager comme déposé
     */
    public function markAsDroppedOff(): void
    {
        $this->update([
            'dropped_off_at' => now()
        ]);

        // Si c'était le dernier passager, marquer le trajet comme terminé
        $remainingBookings = $this->trip->confirmedBookings()
            ->whereNull('dropped_off_at')
            ->where('id', '!=', $this->id)
            ->count();

        if ($remainingBookings === 0) {
            $this->trip->markAsCompleted();
        }
    }

    /**
     * Vérifie si la réservation peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
               $this->trip->departure_date > now()->addHours(2);
    }

    /**
     * Obtient le conducteur via le trajet
     */
    public function getDriverAttribute(): User
    {
        return $this->trip->driver;
    }

    /**
     * Vérifie si le paiement est complété
     */
    public function isPaid(): bool
    {
        return $this->payments()->where('status', 'completed')->exists();
    }

    /**
     * Prix unitaire par siège
     */
    public function getPricePerSeatAttribute(): float
    {
        return $this->seats_booked > 0 ? $this->total_amount / $this->seats_booked : 0;
    }
}
