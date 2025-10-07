<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'payer_id',
        'receiver_id',
        'amount',
        'payment_method',
        'provider',
        'transaction_id',
        'external_reference',
        'phone_number',
        'status',
        'failure_reason',
        'provider_response',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'processed_at',
        'confirmed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'provider_response' => 'array',
        'processed_at' => 'datetime',
        'confirmed_at' => 'datetime'
    ];

    // Relations

    /**
     * Réservation associée
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Utilisateur qui effectue le paiement
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * Utilisateur qui reçoit le paiement
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // Méthodes utilitaires

    /**
     * Vérifier si le paiement est complété
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Vérifier si le paiement a échoué
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Vérifier si le paiement est en attente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Marquer le paiement comme complété
     */
    public function markAsCompleted(string $externalReference = null): void
    {
        $this->update([
            'status' => 'completed',
            'external_reference' => $externalReference,
            'confirmed_at' => now()
        ]);
    }

    /**
     * Marquer le paiement comme échoué
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason
        ]);
    }

    /**
     * Calculer la commission
     */
    public function calculateCommission(): void
    {
        $this->commission_amount = ($this->amount * $this->commission_rate) / 100;
        $this->net_amount = $this->amount - $this->commission_amount;
        $this->save();
    }

    /**
     * Obtenir le nom du prestataire de paiement
     */
    public function getProviderNameAttribute(): string
    {
        return match($this->provider) {
            'mtn' => 'MTN Mobile Money',
            'orange' => 'Orange Money',
            'express_union' => 'Express Union',
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            default => ucfirst($this->provider ?? 'Inconnu')
        };
    }

    /**
     * Obtenir le nom de la méthode de paiement
     */
    public function getPaymentMethodNameAttribute(): string
    {
        return match($this->payment_method) {
            'mobile_money' => 'Mobile Money',
            'bank_card' => 'Carte bancaire',
            'bank_transfer' => 'Virement bancaire',
            'cash' => 'Espèces',
            default => ucfirst($this->payment_method)
        };
    }

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();
        
        // Générer automatiquement un ID de transaction unique
        static::creating(function ($payment) {
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = 'TXN' . strtoupper(Str::random(12));
            }
            
            // Calculer la commission automatiquement
            $payment->calculateCommission();
        });
    }
}