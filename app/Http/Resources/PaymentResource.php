<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'external_reference' => $this->external_reference,

            // Montants
            'amount' => (float) $this->amount,
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => (float) $this->commission_amount,
            'net_amount' => (float) $this->net_amount,
            'formatted_amount' => number_format($this->amount, 0, ',', ' ') . ' FCFA',
            'formatted_net_amount' => number_format($this->net_amount, 0, ',', ' ') . ' FCFA',

            // Méthode et prestataire
            'payment_method' => $this->payment_method,
            'payment_method_name' => $this->payment_method_name,
            'provider' => $this->provider,
            'provider_name' => $this->provider_name,
            'phone_number' => $this->phone_number,

            // Statut
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'failure_reason' => $this->failure_reason,
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'is_pending' => $this->isPending(),

            // Utilisateurs
            'payer' => $this->when($this->relationLoaded('payer'), function () {
                return [
                    'id' => $this->payer->id,
                    'name' => $this->payer->name,
                    'profile_photo_url' => $this->payer->profile_photo_url,
                ];
            }),

            'receiver' => $this->when($this->relationLoaded('receiver'), function () {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->name,
                    'profile_photo_url' => $this->receiver->profile_photo_url,
                ];
            }),

            // Réservation et trajet
            'booking' => $this->when($this->relationLoaded('booking'), function () {
                return [
                    'id' => $this->booking->id,
                    'seats_booked' => $this->booking->seats_booked,
                    'trip' => $this->when($this->booking->relationLoaded('trip'), [
                        'id' => $this->booking->trip->id,
                        'departure_city' => $this->booking->trip->departure_city,
                        'arrival_city' => $this->booking->trip->arrival_city,
                        'departure_date' => $this->booking->trip->departure_date->format('d/m/Y H:i'),
                    ])
                ];
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_ago' => $this->created_at?->diffForHumans(),

            // Informations pour l'affichage
            'display_info' => $this->getDisplayInfo($request->user()),

            // Badges
            'badges' => $this->getPaymentBadges(),

            // Actions possibles
            'actions' => $this->getAvailableActions($request->user()),
        ];
    }

    /**
     * Obtenir le libellé du statut
     */
    private function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'En attente',
            'processing' => 'En cours de traitement',
            'completed' => 'Complété',
            'failed' => 'Échoué',
            'cancelled' => 'Annulé',
            'refunded' => 'Remboursé',
            default => ucfirst($this->status)
        };
    }

    /**
     * Obtenir les informations d'affichage selon l'utilisateur
     */
    private function getDisplayInfo($currentUser): array
    {
        if (!$currentUser) {
            return [];
        }

        $isPayer = $this->payer_id === $currentUser->id;
        $isReceiver = $this->receiver_id === $currentUser->id;

        $info = [
            'user_role' => $isPayer ? 'payer' : ($isReceiver ? 'receiver' : 'unknown'),
            'direction' => $isPayer ? 'outgoing' : 'incoming',
        ];

        if ($isPayer) {
            $info['title'] = 'Paiement à ' . ($this->receiver->name ?? 'Conducteur');
            $info['subtitle'] = 'Réservation #' . $this->booking_id;
            $info['amount_display'] = '- ' . $this->formatted_amount;
            $info['color'] = 'red';
            $info['icon'] = 'arrow-up';
        } else {
            $info['title'] = 'Paiement de ' . ($this->payer->name ?? 'Passager');
            $info['subtitle'] = 'Commission déduite: ' . number_format($this->commission_amount, 0, ',', ' ') . ' FCFA';
            $info['amount_display'] = '+ ' . $this->formatted_net_amount;
            $info['color'] = 'green';
            $info['icon'] = 'arrow-down';
        }

        return $info;
    }

    /**
     * Obtenir les badges du paiement
     */
    private function getPaymentBadges(): array
    {
        $badges = [];

        // Badge selon le statut
        switch ($this->status) {
            case 'completed':
                $badges[] = [
                    'type' => 'completed',
                    'label' => 'Complété',
                    'color' => 'green',
                    'icon' => 'check-circle'
                ];
                break;
            case 'pending':
                $badges[] = [
                    'type' => 'pending',
                    'label' => 'En attente',
                    'color' => 'yellow',
                    'icon' => 'clock'
                ];
                break;
            case 'failed':
                $badges[] = [
                    'type' => 'failed',
                    'label' => 'Échoué',
                    'color' => 'red',
                    'icon' => 'x-circle'
                ];
                break;
        }

        // Badge selon la méthode
        if ($this->payment_method === 'mobile_money') {
            $badges[] = [
                'type' => 'mobile_money',
                'label' => $this->provider_name,
                'color' => $this->provider === 'mtn' ? 'yellow' : 'orange',
                'icon' => 'smartphone'
            ];
        } elseif ($this->payment_method === 'cash') {
            $badges[] = [
                'type' => 'cash',
                'label' => 'Espèces',
                'color' => 'gray',
                'icon' => 'banknote'
            ];
        }

        // Badge instantané
        if ($this->created_at && $this->confirmed_at &&
            $this->confirmed_at->diffInMinutes($this->created_at) < 2) {
            $badges[] = [
                'type' => 'instant',
                'label' => 'Instantané',
                'color' => 'blue',
                'icon' => 'zap'
            ];
        }

        return $badges;
    }

    /**
     * Obtenir les actions disponibles selon l'utilisateur
     */
    private function getAvailableActions($currentUser): array
    {
        if (!$currentUser) {
            return [];
        }

        $actions = [];

        // Actions pour le payeur
        if ($this->payer_id === $currentUser->id) {
            if ($this->status === 'pending') {
                $actions[] = [
                    'type' => 'retry_payment',
                    'label' => 'Réessayer le paiement',
                    'color' => 'blue',
                    'endpoint' => '/api/v1/payments/retry/' . $this->id
                ];

                $actions[] = [
                    'type' => 'cancel_payment',
                    'label' => 'Annuler',
                    'color' => 'red',
                    'endpoint' => '/api/v1/payments/' . $this->id . '/cancel'
                ];
            }
        }

        // Actions pour le bénéficiaire
        if ($this->receiver_id === $currentUser->id) {
            if ($this->status === 'completed') {
                $actions[] = [
                    'type' => 'download_receipt',
                    'label' => 'Télécharger le reçu',
                    'color' => 'gray',
                    'endpoint' => '/api/v1/payments/' . $this->id . '/receipt'
                ];
            }
        }

        // Actions communes
        if ($this->status === 'completed') {
            $actions[] = [
                'type' => 'view_details',
                'label' => 'Voir les détails',
                'color' => 'blue',
                'endpoint' => '/api/v1/payments/' . $this->id
            ];
        }

        return $actions;
    }
}
