<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Notification $notification
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: $this->notification->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'notification' => $this->notification,
                'user' => $this->notification->user,
                'actionUrl' => $this->getActionUrl(),
                'appName' => config('app.name'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Obtenir l'URL d'action selon le type de notification
     */
    private function getActionUrl(): ?string
    {
        $baseUrl = config('app.url');

        return match($this->notification->type) {
            'booking_confirmed', 'booking_cancelled' =>
                $baseUrl . "/bookings/{$this->notification->booking_id}",
            'trip_starting_soon' =>
                $baseUrl . "/trips/{$this->notification->trip_id}",
            'payment_received' =>
                $baseUrl . "/payments?booking_id={$this->notification->booking_id}",
            'new_message' =>
                $baseUrl . "/messages/{$this->notification->related_user_id}",
            default => $baseUrl . "/notifications"
        };
    }
}
