<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'rater_id',
        'rated_id', 
        'rating',
        'comment',
        'type'
    ];

    protected $casts = [
        'rating' => 'integer'
    ];

    // Relations

    /**
     * Réservation associée à cette évaluation
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Utilisateur qui donne l'évaluation
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /**
     * Utilisateur qui reçoit l'évaluation
     */
    public function rated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_id');
    }

    // Méthodes utilitaires

    /**
     * Vérifier si c'est une évaluation positive (4-5 étoiles)
     */
    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    /**
     * Vérifier si c'est une évaluation négative (1-2 étoiles)
     */
    public function isNegative(): bool
    {
        return $this->rating <= 2;
    }

    /**
     * Obtenir le texte de l'évaluation
     */
    public function getRatingTextAttribute(): string
    {
        return match($this->rating) {
            1 => 'Très mauvais',
            2 => 'Mauvais', 
            3 => 'Moyen',
            4 => 'Bon',
            5 => 'Excellent',
            default => 'Non évalué'
        };
    }

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();
        
        // Après création d'une évaluation, mettre à jour la note moyenne de l'utilisateur évalué
        static::created(function ($rating) {
            $rating->rated->updateAverageRating();
        });

        // Après suppression d'une évaluation, mettre à jour la note moyenne
        static::deleted(function ($rating) {
            $rating->rated->updateAverageRating();
        });
    }
}