<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'related_user_id',
        'booking_id',
        'trip_id',
        'is_read',
        'read_at',
        'priority',
        'sent_push',
        'sent_push_at',
        'push_response'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_push' => 'boolean',
        'sent_push_at' => 'datetime',
        'push_response' => 'array'
    ];

    // Relations

    /**
     * Utilisateur qui reçoit la notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Utilisateur lié à la notification (optionnel)
     */
    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    /**
     * Réservation liée (optionnel)
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Trajet lié (optionnel)
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    // Méthodes utilitaires

    /**
     * Marquer comme lue
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    /**
     * Obtenir l'icône selon le type
     */
    public function getIconAttribute(): string
    {
        return match($this->type) {
            'booking_confirmed' => 'check-circle',
            'booking_cancelled' => 'x-circle',
            'trip_started' => 'play-circle',
            'trip_completed' => 'flag',
            'payment_received' => 'dollar-sign',
            'payment_failed' => 'alert-circle',
            'new_message' => 'message-circle',
            'new_rating' => 'star',
            'trip_reminder' => 'clock',
            'user_verified' => 'shield-check',
            default => 'info'
        };
    }

    /**
     * Obtenir la couleur selon le type
     */
    public function getColorAttribute(): string
    {
        return match($this->type) {
            'booking_confirmed', 'payment_received', 'trip_completed' => 'green',
            'booking_cancelled', 'payment_failed' => 'red',
            'trip_started', 'new_message' => 'blue',
            'new_rating' => 'yellow',
            'trip_reminder' => 'orange',
            'user_verified' => 'purple',
            default => 'gray'
        };
    }

    /**
     * Obtenir l'URL d'action (où rediriger quand on clique)
     */
    public function getActionUrlAttribute(): ?string
    {
        return match($this->type) {
            'booking_confirmed', 'booking_cancelled' => "/bookings/{$this->booking_id}",
            'trip_started', 'trip_completed' => "/trips/{$this->trip_id}",
            'payment_received', 'payment_failed' => "/payments?booking_id={$this->booking_id}",
            'new_message' => "/messages/{$this->related_user_id}",
            'new_rating' => "/ratings/received",
            'trip_reminder' => "/trips/{$this->trip_id}",
            default => null
        };
    }

    /**
     * Créer une notification
     */
    public static function createNotification(array $data): self
    {
        return self::create([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
            'related_user_id' => $data['related_user_id'] ?? null,
            'booking_id' => $data['booking_id'] ?? null,
            'trip_id' => $data['trip_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal'
        ]);
    }

    /**
     * Envoyer la notification push (à implémenter avec Firebase)
     */
    public function sendPush(): bool
    {
        if ($this->sent_push) {
            return true;
        }

        try {
            // TODO: Implémenter l'envoi de notification push avec Firebase
            // Pour l'instant, on simule

            $this->update([
                'sent_push' => true,
                'sent_push_at' => now(),
                'push_response' => [
                    'success' => true,
                    'message' => 'Simulation: notification envoyée'
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            $this->update([
                'push_response' => [
                    'success' => false,
                    'error' => $e->getMessage()
                ]
            ]);

            return false;
        }
    }
}
