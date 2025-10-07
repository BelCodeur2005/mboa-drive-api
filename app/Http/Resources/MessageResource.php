<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->formatted_message,
            'original_message' => $this->message,
            'type' => $this->type,
            'booking_id' => $this->booking_id,

            // Utilisateurs
            'sender' => $this->when($this->relationLoaded('sender'), function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'profile_photo_url' => $this->sender->profile_photo_url,
                ];
            }),

            'recipient' => $this->when($this->relationLoaded('recipient'), function () {
                return [
                    'id' => $this->recipient->id,
                    'name' => $this->recipient->name,
                    'profile_photo_url' => $this->recipient->profile_photo_url,
                ];
            }),

            // États du message
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toISOString(),
            'is_from_me' => $this->when($request->user(), function () use ($request) {
                return $this->sender_id === $request->user()->id;
            }),

            // Métadonnées selon le type
            'metadata' => $this->metadata,
            'location' => $this->when($this->type === 'location', $this->location),

            // Informations d'affichage
            'display_info' => $this->getDisplayInfo(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'created_ago' => $this->created_at?->diffForHumans(),
            'time_display' => $this->getTimeDisplay(),

            // Réservation associée (si applicable)
            'booking_info' => $this->when($this->booking_id && $this->relationLoaded('booking'), function () {
                return [
                    'id' => $this->booking->id,
                    'trip_id' => $this->booking->trip_id,
                    'status' => $this->booking->status,
                ];
            }),
        ];
    }

    /**
     * Obtenir les informations d'affichage du message
     */
    private function getDisplayInfo(): array
    {
        $info = [
            'bubble_color' => $this->getBubbleColor(),
            'text_color' => $this->getTextColor(),
            'alignment' => request()->user() && $this->sender_id === request()->user()->id ? 'right' : 'left',
            'show_avatar' => request()->user() && $this->sender_id !== request()->user()->id,
            'show_time' => true,
            'show_read_status' => request()->user() && $this->sender_id === request()->user()->id,
        ];

        // Icône selon le type
        switch ($this->type) {
            case 'location':
                $info['icon'] = 'map-pin';
                $info['icon_color'] = 'red';
                break;
            case 'image':
                $info['icon'] = 'image';
                $info['icon_color'] = 'blue';
                break;
            case 'system':
                $info['icon'] = 'info';
                $info['icon_color'] = 'gray';
                $info['is_system'] = true;
                $info['alignment'] = 'center';
                break;
            default:
                $info['icon'] = null;
        }

        return $info;
    }

    /**
     * Obtenir la couleur de la bulle du message
     */
    private function getBubbleColor(): string
    {
        if ($this->type === 'system') {
            return 'bg-gray-100';
        }

        if (request()->user() && $this->sender_id === request()->user()->id) {
            return 'bg-blue-500'; // Messages envoyés
        }

        return 'bg-gray-200'; // Messages reçus
    }

    /**
     * Obtenir la couleur du texte
     */
    private function getTextColor(): string
    {
        if ($this->type === 'system') {
            return 'text-gray-600';
        }

        if (request()->user() && $this->sender_id === request()->user()->id) {
            return 'text-white'; // Messages envoyés
        }

        return 'text-gray-800'; // Messages reçus
    }

    /**
     * Obtenir l'affichage de l'heure
     */
    private function getTimeDisplay(): array
    {
        if (!$this->created_at) {
            return ['short' => '', 'full' => ''];
        }

        $now = now();
        $messageDate = $this->created_at;

        // Si c'est aujourd'hui, afficher seulement l'heure
        if ($messageDate->isToday()) {
            return [
                'short' => $messageDate->format('H:i'),
                'full' => $messageDate->format('H:i'),
                'is_today' => true
            ];
        }

        // Si c'est hier
        if ($messageDate->isYesterday()) {
            return [
                'short' => 'Hier',
                'full' => 'Hier ' . $messageDate->format('H:i'),
                'is_yesterday' => true
            ];
        }

        // Si c'est cette semaine
        if ($messageDate->gt($now->startOfWeek())) {
            return [
                'short' => $messageDate->translatedFormat('D'),
                'full' => $messageDate->translatedFormat('l H:i'),
                'is_this_week' => true
            ];
        }

        // Si c'est cette année
        if ($messageDate->year === $now->year) {
            return [
                'short' => $messageDate->format('d/m'),
                'full' => $messageDate->format('d/m/Y H:i'),
                'is_this_year' => true
            ];
        }

        // Sinon, affichage complet
        return [
            'short' => $messageDate->format('d/m/Y'),
            'full' => $messageDate->format('d/m/Y H:i'),
            'is_old' => true
        ];
    }
}
