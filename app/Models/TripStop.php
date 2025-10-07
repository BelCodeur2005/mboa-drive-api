<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'city',
        'address',
        'latitude',
        'longitude',
        'order',
        'estimated_arrival_time',
        'estimated_departure_time',
        'additional_cost'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'estimated_arrival_time' => 'datetime:H:i',
        'estimated_departure_time' => 'datetime:H:i',
        'additional_cost' => 'decimal:2'
    ];

    // Relations

    /**
     * Trajet associé à ce point d'arrêt
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    // Méthodes utilitaires

    /**
     * Obtenir l'adresse complète
     */
    public function getFullAddressAttribute(): string
    {
        return $this->address . ', ' . $this->city;
    }

    /**
     * Vérifier si le point d'arrêt a des coordonnées GPS
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude && $this->longitude;
    }

    /**
     * Obtenir les coordonnées sous forme de tableau
     */
    public function getCoordinatesAttribute(): ?array
    {
        if ($this->hasCoordinates()) {
            return [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude
            ];
        }

        return null;
    }

    /**
     * Vérifier si le point d'arrêt a un coût additionnel
     */
    public function hasAdditionalCost(): bool
    {
        return $this->additional_cost > 0;
    }

    /**
     * Formater le coût additionnel
     */
    public function getFormattedAdditionalCostAttribute(): string
    {
        if ($this->hasAdditionalCost()) {
            return number_format($this->additional_cost, 0, ',', ' ') . ' FCFA';
        }

        return 'Gratuit';
    }
}
