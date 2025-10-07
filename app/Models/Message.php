<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'booking_id',
        'message',
        'type',
        'metadata',
        'is_read',
        'read_at',
        'is_deleted_by_sender',
        'is_deleted_by_recipient'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_deleted_by_sender' => 'boolean',
        'is_deleted_by_recipient' => 'boolean'
    ];

    // Relations

    /**
     * Expéditeur du message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Destinataire du message
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Réservation associée (optionnel)
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // Scopes

    /**
     * Messages entre deux utilisateurs
     */
    public function scopeBetweenUsers(Builder $query, int $user1, int $user2): Builder
    {
        return $query->where(function ($q) use ($user1, $user2) {
            $q->where('sender_id', $user1)->where('recipient_id', $user2);
        })->orWhere(function ($q) use ($user1, $user2) {
            $q->where('sender_id', $user2)->where('recipient_id', $user1);
        });
    }

    /**
     * Messages non lus
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Messages pour un utilisateur donné
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)->where('is_deleted_by_sender', false);
        })->orWhere(function ($q) use ($userId) {
            $q->where('recipient_id', $userId)->where('is_deleted_by_recipient', false);
        });
    }

    /**
     * Messages d'une conversation
     */
    public function scopeConversation(Builder $query, int $userId, int $otherUserId): Builder
    {
        return $query->betweenUsers($userId, $otherUserId)
                    ->where(function ($q) use ($userId) {
                        $q->where(function ($subQ) use ($userId) {
                            $subQ->where('sender_id', $userId)
                                 ->where('is_deleted_by_sender', false);
                        })->orWhere(function ($subQ) use ($userId) {
                            $subQ->where('recipient_id', $userId)
                                 ->where('is_deleted_by_recipient', false);
                        });
                    });
    }

    // Méthodes utilitaires

    /**
     * Marquer le message comme lu
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
     * Supprimer le message pour l'expéditeur
     */
    public function deleteForSender(): void
    {
        $this->update(['is_deleted_by_sender' => true]);
        
        // Si supprimé par les deux parties, supprimer définitivement
        if ($this->is_deleted_by_recipient) {
            $this->delete();
        }
    }

    /**
     * Supprimer le message pour le destinataire
     */
    public function deleteForRecipient(): void
    {
        $this->update(['is_deleted_by_recipient' => true]);
        
        // Si supprimé par les deux parties, supprimer définitivement
        if ($this->is_deleted_by_sender) {
            $this->delete();
        }
    }

    /**
     * Vérifier si l'utilisateur peut voir ce message
     */
    public function canBeSeenBy(int $userId): bool
    {
        if ($this->sender_id === $userId) {
            return !$this->is_deleted_by_sender;
        }
        
        if ($this->recipient_id === $userId) {
            return !$this->is_deleted_by_recipient;
        }
        
        return false;
    }

    /**
     * Obtenir l'autre participant de la conversation
     */
    public function getOtherParticipant(int $currentUserId): User
    {
        return $this->sender_id === $currentUserId ? $this->recipient : $this->sender;
    }

    /**
     * Formater le message selon son type
     */
    public function getFormattedMessageAttribute(): string
    {
        return match($this->type) {
            'location' => '📍 Position partagée',
            'image' => '🖼️ Image',
            'system' => $this->message,
            default => $this->message
        };
    }

    /**
     * Obtenir les coordonnées GPS si le message est de type location
     */
    public function getLocationAttribute(): ?array
    {
        if ($this->type === 'location' && isset($this->metadata['latitude'], $this->metadata['longitude'])) {
            return [
                'latitude' => $this->metadata['latitude'],
                'longitude' => $this->metadata['longitude'],
                'address' => $this->metadata['address'] ?? null
            ];
        }
        
        return null;
    }
}