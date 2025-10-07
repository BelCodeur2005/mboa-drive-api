<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_photo',
        'user_type',
        'date_of_birth',
        'gender',
        'bio',
        'city',
        'is_verified',
        'phone_verified',
        'is_active',
        'emergency_contacts',
        'preferences'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'is_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'is_active' => 'boolean',
        'average_rating' => 'decimal:2',
        'last_active_at' => 'datetime',
        'emergency_contacts' => 'array',
        'preferences' => 'array',
    ];

    // Relations

    /**
     * Véhicules du conducteur
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Véhicule principal/actif
     */
    public function primaryVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->where('is_active', true)->latest();
    }

    /**
     * Trajets créés par ce conducteur
     */
    public function driverTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    /**
     * Réservations effectuées par ce passager
     */
    public function passengerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    /**
     * Évaluations données par cet utilisateur
     */
    public function givenRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    /**
     * Évaluations reçues par cet utilisateur
     */
    public function receivedRatings(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_id');
    }

    /**
     * Messages envoyés
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages reçus
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    // Méthodes utilitaires

    /**
     * Vérifie si l'utilisateur est un conducteur
     */
    public function isDriver(): bool
    {
        return in_array($this->user_type, ['driver', 'both']);
    }

    /**
     * Vérifie si l'utilisateur est un passager
     */
    public function isPassenger(): bool
    {
        return in_array($this->user_type, ['passenger', 'both']);
    }

    /**
     * Met à jour la note moyenne après une nouvelle évaluation
     */
    public function updateAverageRating(): void
    {
        $ratings = $this->receivedRatings;
        $this->total_ratings = $ratings->count();
        $this->average_rating = $ratings->avg('rating') ?? 5.0;
        $this->save();
    }

    /**
     * Obtient l'URL complète de la photo de profil
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo ? asset('storage/' . $this->profile_photo) : null;
    }

    /**
     * Marque l'utilisateur comme actif
     */
    public function markAsActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }
}
