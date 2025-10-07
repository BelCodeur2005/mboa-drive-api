<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand',
        'model',
        'color',
        'license_plate',
        'year',
        'seats',
        'fuel_type',
        'air_conditioning',
        'photos',
        'description',
        'is_verified',
        'is_active'
    ];

    protected $casts = [
        'air_conditioning' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'photos' => 'array'
    ];

    // Relations

    /**
     * Propriétaire du véhicule
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Trajets effectués avec ce véhicule
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    // Méthodes utilitaires

    /**
     * Nom complet du véhicule
     */
    public function getFullNameAttribute(): string
    {
        return $this->brand . ' ' . $this->model . ' (' . $this->year . ')';
    }

    /**
     * Nombre de places disponibles pour les passagers
     */
    public function getAvailableSeatsAttribute(): int
    {
        return $this->seats - 1; // -1 pour le conducteur
    }

    /**
     * URLs des photos
     */
    public function getPhotoUrlsAttribute(): array
    {
        if (!$this->photos) {
            return [];
        }

        return array_map(function ($photo) {
            return asset('storage/' . $photo);
        }, $this->photos);
    }

    /**
     * Photo principale
     */
    public function getMainPhotoUrlAttribute(): ?string
    {
        $urls = $this->photo_urls;
        return count($urls) > 0 ? $urls[0] : null;
    }
}
