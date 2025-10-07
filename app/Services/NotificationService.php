<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Mail\NotificationMail;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Twilio\Rest\Client;

class NotificationService
{
    /**
     * Créer et envoyer une notification complète
     */
    public function sendNotification(int $userId, string $type, array $data): Notification
    {
        // 1. Créer la notification en base
        $notification = Notification::createNotification([
            'user_id' => $userId,
            'type' => $type,
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? null,
            'related_user_id' => $data['related_user_id'] ?? null,
            'booking_id' => $data['booking_id'] ?? null,
            'trip_id' => $data['trip_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal'
        ]);

        // 2. Envoyer la notification push
        if ($data['send_push'] ?? true) {
            $this->sendPushNotification($notification);
        }

        // 3. Envoyer SMS si priorité haute
        if ($notification->priority === 'high' && ($data['send_sms'] ?? false)) {
            $user = User::find($userId);
            if ($user && $user->phone) {
                $this->sendSMS($user->phone, $notification->title);
            }
        }

        // 4. Envoyer email si configuré
        if ($data['send_email'] ?? false) {
            $this->sendEmail($notification);
        }

        // 5. Diffuser en temps réel via WebSockets
        if ($data['broadcast'] ?? true) {
            broadcast(new NotificationSent($notification));
        }

        return $notification;
    }

    /**
     * Envoyer une notification push via Firebase
     */
    public function sendPushNotification(Notification $notification): bool
    {
        try {
            $user = $notification->user;

            // Vérifier si les notifications push sont activées pour cet utilisateur
            if (!$user->push_notifications_enabled) {
                Log::info("Notifications push désactivées pour l'utilisateur {$user->id}");
                return false;
            }

            // Récupérer le token FCM de l'utilisateur
            $fcmToken = $user->fcm_token;
            if (!$fcmToken) {
                Log::warning("Pas de token FCM pour l'utilisateur {$user->id}");
                return false;
            }

            // Vérifier que Firebase est configuré
            if (!app()->bound('firebase.messaging')) {
                Log::error("Service Firebase Messaging non configuré");
                return false;
            }

            $messaging = app('firebase.messaging');

            $firebaseNotification = FirebaseNotification::create(
                $notification->title,
                $notification->message
            );

            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($firebaseNotification)
                ->withData([
                    'type' => $notification->type,
                    'notification_id' => (string) $notification->id,
                    'action_url' => $notification->action_url ?? '',
                    'data' => json_encode($notification->data ?? [])
                ]);

            // Ajouter un badge selon le type
            $badgeCount = $user->notifications()->where('is_read', false)->count();
            $message = $message->withApnsConfig([
                'aps' => [
                    'badge' => $badgeCount,
                    'sound' => $this->getSoundForType($notification->type)
                ]
            ]);

            // Configuration Android
            $message = $message->withAndroidConfig([
                'notification' => [
                    'channel_id' => 'mboa_drive_notifications',
                    'sound' => $this->getSoundForType($notification->type),
                    'priority' => $notification->priority === 'high' ? 'high' : 'normal',
                    'vibrate_timings' => $this->getVibrationPattern($notification->priority),
                ]
            ]);

            $result = $messaging->send($message);

            // Marquer comme envoyé
            $notification->update([
                'sent_push' => true,
                'sent_push_at' => now(),
                'push_response' => [
                    'success' => true,
                    'firebase_response' => $result
                ]
            ]);

            Log::info("Push notification envoyée avec succès pour l'utilisateur {$user->id}");
            return true;

        } catch (\Kreait\Firebase\Exception\InvalidArgumentException $e) {
            Log::error("Token FCM invalide pour l'utilisateur {$notification->user_id}: " . $e->getMessage());

            // Marquer le token comme invalide
            $notification->user->update(['fcm_token' => null]);

        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            Log::error("Erreur Firebase Messaging: " . $e->getMessage());

        } catch (\Exception $e) {
            Log::error("Erreur push notification: " . $e->getMessage());
        }

        $notification->update([
            'push_response' => [
                'success' => false,
                'error' => $e->getMessage() ?? 'Erreur inconnue'
            ]
        ]);

        return false;
    }

    /**
     * Envoyer un SMS via un service
     */
    public function sendSMS(string $phone, string $message): bool
    {
        try {
            // Vérifier que les SMS sont activés
            if (!config('services.notifications.channels.sms', true)) {
                return false;
            }

            // Nettoyer le numéro de téléphone
            $phone = $this->cleanPhoneNumber($phone);

            if (app()->environment('production')) {
                // Utiliser Twilio en production
                if (config('services.twilio.sid')) {
                    return $this->sendTwilioSMS($phone, $message);
                }

                // Ou Nexmo/Vonage
                if (config('services.nexmo.key')) {
                    return $this->sendNexmoSMS($phone, $message);
                }
            }

            // Simulation en développement
            Log::info("SMS simulé envoyé à $phone: $message");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur SMS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer SMS via Twilio
     */
    private function sendTwilioSMS(string $phone, string $message): bool
    {
        try {
            $twilio = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $twilio->messages->create($phone, [
                'from' => config('services.twilio.from'),
                'body' => $message
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur Twilio SMS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer SMS via Nexmo/Vonage
     */
    private function sendNexmoSMS(string $phone, string $message): bool
    {
        try {
            // Implémentation Nexmo (si installé)
            // $client = new \Vonage\Client(new \Vonage\Client\Credentials\Basic(...));
            // $response = $client->sms()->send(...);

            Log::info("Nexmo SMS non implémenté: $phone - $message");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur Nexmo SMS: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer un email de notification
     */
    public function sendEmail(Notification $notification): bool
    {
        try {
            $user = $notification->user;

            if (!$user->email) {
                Log::warning("Pas d'email pour l'utilisateur {$user->id}");
                return false;
            }

            Mail::to($user->email)->send(new NotificationMail($notification));

            Log::info("Email de notification envoyé à {$user->email}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifications spécifiques par type d'événement
     */
    public function bookingConfirmed(int $passengerId, $booking): void
    {
        $this->sendNotification($passengerId, 'booking_confirmed', [
            'title' => 'Réservation confirmée !',
            'message' => "Votre réservation pour {$booking->trip->departure_city} → {$booking->trip->arrival_city} a été confirmée.",
            'booking_id' => $booking->id,
            'related_user_id' => $booking->trip->driver_id,
            'priority' => 'high',
            'send_push' => true,
            'send_sms' => true,
            'data' => [
                'departure_time' => $booking->trip->departure_date->toISOString(),
                'driver_name' => $booking->trip->driver->name,
                'driver_phone' => $booking->trip->driver->phone,
                'pickup_address' => $booking->pickup_address ?? $booking->trip->departure_address
            ]
        ]);
    }

    public function tripStartingSoon(int $driverId, $trip): void
    {
        $passengers = $trip->confirmedBookings->pluck('passenger');

        $this->sendNotification($driverId, 'trip_starting_soon', [
            'title' => 'Votre trajet commence dans 30 min',
            'message' => "N'oubliez pas votre trajet {$trip->departure_city} → {$trip->arrival_city}",
            'trip_id' => $trip->id,
            'priority' => 'high',
            'send_push' => true,
            'data' => [
                'departure_time' => $trip->departure_date->toISOString(),
                'passengers_count' => $passengers->count(),
                'passengers' => $passengers->map(fn($p) => [
                    'name' => $p->name,
                    'phone' => $p->phone
                ])->toArray()
            ]
        ]);

        // Notifier aussi les passagers
        foreach ($passengers as $passenger) {
            $this->sendNotification($passenger->id, 'trip_starting_soon', [
                'title' => 'Votre trajet commence bientôt',
                'message' => "Soyez prêt ! Départ dans 30 minutes depuis {$trip->departure_address}",
                'trip_id' => $trip->id,
                'related_user_id' => $driverId,
                'priority' => 'high',
                'send_push' => true,
                'data' => [
                    'driver_name' => $trip->driver->name,
                    'driver_phone' => $trip->driver->phone,
                    'departure_address' => $trip->departure_address
                ]
            ]);
        }
    }

    public function paymentReceived(int $driverId, $payment): void
    {
        $this->sendNotification($driverId, 'payment_received', [
            'title' => "Paiement reçu - " . number_format($payment->net_amount, 0, ',', ' ') . " FCFA",
            'message' => "Vous avez reçu un paiement de {$payment->payer->name}",
            'booking_id' => $payment->booking_id,
            'related_user_id' => $payment->payer_id,
            'priority' => 'normal',
            'send_push' => true,
            'data' => [
                'amount' => $payment->amount,
                'net_amount' => $payment->net_amount,
                'commission' => $payment->commission_amount,
                'payment_method' => $payment->payment_method_name
            ]
        ]);
    }

    public function newMessage(int $recipientId, $message): void
    {
        $this->sendNotification($recipientId, 'new_message', [
            'title' => "Message de {$message->sender->name}",
            'message' => substr($message->message, 0, 50) . (strlen($message->message) > 50 ? '...' : ''),
            'related_user_id' => $message->sender_id,
            'booking_id' => $message->booking_id,
            'priority' => 'normal',
            'send_push' => true,
            'data' => [
                'sender_name' => $message->sender->name,
                'message_type' => $message->type,
                'conversation_id' => min($message->sender_id, $recipientId) . '_' . max($message->sender_id, $recipientId)
            ]
        ]);
    }

    /**
     * Obtenir le son de notification selon le type
     */
    private function getSoundForType(string $type): string
    {
        return match($type) {
            'booking_confirmed', 'payment_received' => 'success',
            'booking_cancelled', 'payment_failed' => 'error',
            'trip_starting_soon' => 'urgent',
            'new_message' => 'message',
            default => 'default'
        };
    }

    /**
     * Obtenir le pattern de vibration selon la priorité
     */
    private function getVibrationPattern(string $priority): array
    {
        return match($priority) {
            'high' => [0, 500, 200, 500, 200, 500],
            'normal' => [0, 300, 100, 300],
            default => [0, 200]
        };
    }

    /**
     * Nettoyer le numéro de téléphone
     */
    private function cleanPhoneNumber(string $phone): string
    {
        // Supprimer espaces et caractères spéciaux
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ajouter le code pays +237 pour le Cameroun si manquant
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '237')) {
                $phone = '+' . $phone;
            } elseif (str_starts_with($phone, '6')) {
                $phone = '+237' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(int $userId): void
    {
        Notification::where('user_id', $userId)
                   ->where('is_read', false)
                   ->update([
                       'is_read' => true,
                       'read_at' => now()
                   ]);
    }

    /**
     * Nettoyer les anciennes notifications (job quotidien)
     */
    public function cleanupOldNotifications(): void
    {
        $deletedCount = Notification::where('created_at', '<', now()->subDays(30))
                                   ->where('is_read', true)
                                   ->delete();

        Log::info("Nettoyage notifications: {$deletedCount} notifications supprimées");
    }

    /**
     * Obtenir les statistiques des notifications
     */
    public function getNotificationStats(int $userId): array
    {
        $user = User::find($userId);

        return [
            'total_notifications' => $user->notifications()->count(),
            'unread_notifications' => $user->notifications()->where('is_read', false)->count(),
            'push_enabled' => $user->push_notifications_enabled,
            'has_fcm_token' => !is_null($user->fcm_token),
            'last_notification' => $user->notifications()->latest()->first()?->created_at?->toISOString(),
            'notifications_by_type' => $user->notifications()
                                          ->selectRaw('type, COUNT(*) as count')
                                          ->groupBy('type')
                                          ->pluck('count', 'type')
                                          ->toArray()
        ];
    }
}
