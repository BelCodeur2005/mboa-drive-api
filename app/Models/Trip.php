<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'departure_city',
        'departure_address',
        'departure_latitude',
        'departure_longitude',
        'arrival_city',
        'arrival_address',
        'arrival_latitude',
        'arrival_longitude',
        'departure_date',
        'arrival_date',
        'is_recurring',
        'recurring_days',
        'available_seats',
        'total_seats',
        'price_per_seat',
        'total_distance',
        'estimated_duration',
        'instant_booking',
        'smoking_allowed',
        'pets_allowed',
        'max_2_in_back',
        'additional_info',
        'status',
        'started_at',
        'completed_at',
        'cancellation_reason'
    ];

    protected $casts = [
        'departure_date' => 'datetime',
        'arrival_date' => 'datetime',
        'is_recurring' => 'boolean',
        'recurring_days' => 'array',
        'price_per_seat' => 'decimal:2',
        'total_distance' => 'decimal:2',
        'instant_booking' => 'boolean',
        'smoking_allowed' => 'boolean',
        'pets_allowed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    // Relations

    /**
     * Conducteur du trajet
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Véhicule utilisé
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Réservations pour ce trajet
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Réservations confirmées
     */
    public function confirmedBookings(): HasMany
    {
        return $this->bookings()->where('status', 'confirmed');
    }

    /**
     * Points d'arrêt intermédiaires
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('order');
    }

    // Scopes

    /**
     * Trajets disponibles (non complets, non annulés)
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
                    ->where('departure_date', '>', now())
                    ->where('available_seats', '>', 0);
    }

    /**
     * Recherche par villes
     */
    public function scopeSearchRoute(Builder $query, string $from, string $to): Builder
    {
        return $query->where('departure_city', 'like', "%{$from}%")
                    ->where('arrival_city', 'like', "%{$to}%");
    }

    /**
     * Recherche par date
     */
    public function scopeOnDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('departure_date', $date->toDateString());
    }

    /**
     * Trajets d'un conducteur
     */
    public function scopeByDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    // Méthodes utilitaires

    /**
     * Nombre de places réservées
     */
    public function getBookedSeatsAttribute(): int
    {
        return $this->confirmedBookings->sum('seats_booked');
    }

    /**
     * Vérifie si le trajet est complet
     */
    public function isFull(): bool
    {
        return $this->available_seats <= $this->booked_seats;
    }

    /**
     * Peut être réservé instantanément
     */
    public function canBeBookedInstantly(): bool
    {
        return $this->instant_booking && !$this->isFull() && $this->status === 'scheduled';
    }

    /**
     * Marque le trajet comme commencé
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
    }

    /**
     * Marque le trajet comme terminé
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Annule le trajet
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ]);

        // Annuler toutes les réservations
        $this->bookings()->where('status', ['pending', 'confirmed'])
             ->update([
                 'status' => 'cancelled',
                 'cancelled_at' => now(),
                 'cancellation_reason' => 'Trip cancelled by driver'
             ]);
    }

    /**
     * Prix total du trajet (toutes les places)
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->price_per_seat * $this->total_seats;
    }

    /**
     * Durée estimée en format lisible
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->estimated_duration) {
            return 'Non estimé';
        }

        $hours = floor($this->estimated_duration / 60);
        $minutes = $this->estimated_duration % 60;

        if ($hours > 0) {
            return $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'min' : '');
        }

        return $minutes . 'min';
    }
}
