<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\NotificationService;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Mettre à jour le profil utilisateur
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'sometimes|string|regex:/^[0-9+\-\s]+$/|unique:users,phone,' . $user->id,
                'date_of_birth' => 'sometimes|date|before:today',
                'gender' => 'sometimes|in:male,female,other',
                'bio' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'user_type' => ['sometimes', Rule::in(['passenger', 'driver', 'both'])],
                'emergency_contacts' => 'sometimes|array|max:3',
                'emergency_contacts.*.name' => 'required_with:emergency_contacts|string|max:100',
                'emergency_contacts.*.phone' => 'required_with:emergency_contacts|string|max:20',
                'preferences' => 'sometimes|array',
            ]);

            $updateData = $request->only([
                'name', 'email', 'phone', 'date_of_birth', 'gender',
                'bio', 'city', 'user_type', 'emergency_contacts', 'preferences'
            ]);

            // Nettoyer les données
            if (isset($updateData['phone'])) {
                $updateData['phone'] = preg_replace('/\s+/', '', $updateData['phone']);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => new UserResource($user->fresh())
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload photo de profil
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $user = $request->user();

            // Supprimer l'ancienne photo si elle existe
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Upload la nouvelle photo
            $filename = 'profiles/' . $user->id . '/' . Str::uuid() . '.' . $request->file('photo')->getClientOriginalExtension();
            $path = $request->file('photo')->storeAs('public', $filename);

            if ($path) {
                $user->update(['profile_photo' => $filename]);

                return response()->json([
                    'success' => true,
                    'message' => 'Photo de profil mise à jour avec succès',
                    'data' => [
                        'profile_photo_url' => asset('storage/' . $filename),
                        'user' => new UserResource($user->fresh())
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de la photo'
            ], 500);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer photo de profil
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
                $user->update(['profile_photo' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les préférences utilisateur
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'music_style' => 'sometimes|string|in:none,gospel,makossa,afrobeat,pop,jazz,classical',
                'conversation' => 'sometimes|string|in:none,quiet,moderate,friendly,chatty',
                'temperature' => 'sometimes|integer|min:16|max:28',
                'smoking_tolerance' => 'sometimes|string|in:no,yes,window_open',
                'pet_tolerance' => 'sometimes|string|in:no,small,all',
                'notifications' => 'sometimes|array',
                'notifications.booking_updates' => 'sometimes|boolean',
                'notifications.trip_reminders' => 'sometimes|boolean',
                'notifications.payment_updates' => 'sometimes|boolean',
                'notifications.new_messages' => 'sometimes|boolean',
            ]);

            $user = $request->user();
            $preferences = array_merge($user->preferences ?? [], $request->all());

            $user->update(['preferences' => $preferences]);

            return response()->json([
                'success' => true,
                'message' => 'Préférences mises à jour avec succès',
                'data' => ['preferences' => $preferences]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des préférences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des utilisateurs
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2',
                'type' => 'sometimes|in:driver,passenger,both'
            ]);

            $query = User::where('is_active', true)
                        ->where('name', 'like', '%' . $request->query . '%');

            if ($request->filled('type')) {
                $query->where(function ($q) use ($request) {
                    $q->where('user_type', $request->type)
                      ->orWhere('user_type', 'both');
                });
            }

            $users = $query->take(20)->get();

            return response()->json([
                'success' => true,
                'data' => UserResource::collection($users)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Signaler un utilisateur
     */
    public function reportUser(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'reported_user_id' => 'required|exists:users,id|different:user_id',
                'reason' => 'required|string|in:inappropriate_behavior,harassment,fraud,fake_profile,other',
                'description' => 'required|string|max:1000',
                'booking_id' => 'sometimes|exists:bookings,id'
            ]);

            $user = $request->user();

            if ($user->id == $request->reported_user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas vous signaler vous-même'
                ], 422);
            }

            // TODO: Créer une table reports pour stocker les signalements
            // Pour l'instant, on peut juste envoyer une notification aux admins

            return response()->json([
                'success' => true,
                'message' => 'Signalement envoyé avec succès. Nous examinerons votre demande.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du signalement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer un feedback
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|in:bug,feature_request,general,complaint',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
                'rating' => 'sometimes|integer|min:1|max:5'
            ]);

            // TODO: Sauvegarder le feedback en base ou l'envoyer par email

            return response()->json([
                'success' => true,
                'message' => 'Merci pour votre feedback ! Nous l\'examinerons bientôt.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques du tableau de bord
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = [];

            if ($user->isDriver()) {
                $stats['driver'] = [
                    'total_trips' => $user->driverTrips()->count(),
                    'completed_trips' => $user->driverTrips()->where('status', 'completed')->count(),
                    'total_earnings' => $user->driverTrips()
                                          ->whereHas('bookings', function ($q) {
                                              $q->where('status', 'completed');
                                          })
                                          ->withSum(['bookings' => function ($q) {
                                              $q->where('status', 'completed');
                                          }], 'total_amount')
                                          ->sum('bookings_sum_total_amount') ?? 0,
                    'average_rating' => $user->average_rating,
                    'active_trips' => $user->driverTrips()->whereIn('status', ['scheduled', 'in_progress'])->count()
                ];
            }

            if ($user->isPassenger()) {
                $stats['passenger'] = [
                    'total_bookings' => $user->passengerBookings()->count(),
                    'completed_bookings' => $user->passengerBookings()->where('status', 'completed')->count(),
                    'total_spent' => $user->passengerBookings()->where('status', 'completed')->sum('total_amount'),
                    'upcoming_bookings' => $user->passengerBookings()
                                               ->whereHas('trip', function ($q) {
                                                   $q->where('departure_date', '>', now());
                                               })
                                               ->where('status', 'confirmed')
                                               ->count()
                ];
            }

            $stats['general'] = [
                'account_created' => $user->created_at->diffForHumans(),
                'profile_completion' => $this->calculateProfileCompletion($user),
                'verification_status' => [
                    'phone_verified' => $user->phone_verified,
                    'is_verified' => $user->is_verified
                ],
                'unread_notifications' => $user->notifications()->where('is_read', false)->count(),
                'unread_messages' => $user->receivedMessages()->where('is_read', false)->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notifications de l'utilisateur
     */
    public function notifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $notifications = $user->notifications()
                                  ->orderBy('created_at', 'desc')
                                  ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'unread_count' => $user->notifications()->where('is_read', false)->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function markNotificationAsRead(Request $request, $notificationId): JsonResponse
    {
        try {
            $user = $request->user();

            $notification = $user->notifications()->findOrFail($notificationId);
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllNotificationsAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $user->notifications()
                 ->where('is_read', false)
                 ->update([
                     'is_read' => true,
                     'read_at' => now()
                 ]);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications ont été marquées comme lues'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nombre de notifications non lues
     */
    public function unreadNotificationsCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = $user->notifications()->where('is_read', false)->count();

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $count]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer le pourcentage de complétion du profil
     */
    private function calculateProfileCompletion(User $user): int
    {
        $fields = [
            'name' => !empty($user->name),
            'email' => !empty($user->email),
            'phone' => !empty($user->phone),
            'profile_photo' => !empty($user->profile_photo),
            'date_of_birth' => !empty($user->date_of_birth),
            'gender' => !empty($user->gender),
            'bio' => !empty($user->bio),
            'city' => !empty($user->city),
            'phone_verified' => $user->phone_verified,
        ];

        if ($user->isDriver()) {
            $fields['has_vehicle'] = $user->vehicles()->count() > 0;
        }

        $completed = array_sum($fields);
        $total = count($fields);

        return round(($completed / $total) * 100);
    }
    /**
     * Enregistrer le token FCM de l'utilisateur
     */
    public function updateFCMToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fcm_token' => 'required|string',
                'device_info' => 'sometimes|array',
                'device_info.platform' => 'sometimes|in:android,ios,web',
                'device_info.app_version' => 'sometimes|string',
                'device_info.device_model' => 'sometimes|string',
            ]);

            $user = $request->user();

            $user->update([
                'fcm_token' => $request->fcm_token,
                'device_info' => $request->device_info ?? null,
                'fcm_token_updated_at' => now()
            ]);

            // S'abonner aux topics Firebase appropriés
            $this->subscribeToTopics($user, $request->fcm_token);

            return response()->json([
                'success' => true,
                'message' => 'Token FCM enregistré avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/désactiver les notifications push
     */
    public function togglePushNotifications(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'enabled' => 'required|boolean'
            ]);

            $user = $request->user();
            $user->update(['push_notifications_enabled' => $request->enabled]);

            if (!$request->enabled && $user->fcm_token) {
                // Se désabonner de tous les topics
                $this->unsubscribeFromAllTopics($user);
            } elseif ($request->enabled && $user->fcm_token) {
                // Se réabonner aux topics appropriés
                $this->subscribeToTopics($user, $user->fcm_token);
            }

            return response()->json([
                'success' => true,
                'message' => $request->enabled ? 'Notifications activées' : 'Notifications désactivées',
                'data' => ['push_notifications_enabled' => $user->push_notifications_enabled]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les préférences de notifications
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $preferences = $user->preferences['notifications'] ?? [
                'booking_updates' => true,
                'trip_reminders' => true,
                'payment_updates' => true,
                'new_messages' => true,
                'promotional' => false,
                'weekly_summary' => true
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'push_notifications_enabled' => $user->push_notifications_enabled,
                    'preferences' => $preferences,
                    'fcm_token_registered' => !is_null($user->fcm_token),
                    'last_token_update' => $user->fcm_token_updated_at?->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des préférences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tester l'envoi d'une notification
     */
    public function testNotification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->fcm_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun token FCM enregistré'
                ], 422);
            }

            $notificationService = app(NotificationService::class);

            $notification = $notificationService->sendNotification($user->id, 'test', [
                'title' => '🧪 Test de notification',
                'message' => 'Cette notification teste votre configuration Firebase.',
                'send_push' => true,
                'priority' => 'normal',
                'data' => [
                    'test' => true,
                    'timestamp' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification de test envoyée',
                'data' => [
                    'notification_id' => $notification->id,
                    'sent_at' => $notification->created_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du test de notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * S'abonner aux topics Firebase appropriés
     */
    private function subscribeToTopics(User $user, string $fcmToken): void
    {
        try {
            $messaging = app('firebase.messaging');

            // Topics de base
            $topics = ['all_users', 'cameroon_users'];

            // Topics selon le type d'utilisateur
            if ($user->isDriver()) {
                $topics[] = 'drivers';
            }
            if ($user->isPassenger()) {
                $topics[] = 'passengers';
            }

            // Topic géographique
            if ($user->city) {
                $cityTopic = strtolower(str_replace(' ', '_', $user->city)) . '_users';
                $topics[] = $cityTopic;
            }

            // Topic utilisateurs vérifiés
            if ($user->is_verified) {
                $topics[] = 'verified_users';
            }

            // S'abonner à tous les topics
            foreach ($topics as $topic) {
                try {
                    $messaging->subscribeToTopic($topic, $fcmToken);
                } catch (\Exception $e) {
                    Log::warning("Erreur abonnement topic $topic: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Erreur abonnement topics: " . $e->getMessage());
        }
    }

    /**
     * Se désabonner de tous les topics
     */
    private function unsubscribeFromAllTopics(User $user): void
    {
        try {
            $messaging = app('firebase.messaging');
            $topics = config('firebase.topics');

            foreach ($topics as $topic) {
                try {
                    $messaging->unsubscribeFromTopic($topic, $user->fcm_token);
                } catch (\Exception $e) {
                    Log::warning("Erreur désabonnement topic $topic: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error("Erreur désabonnement topics: " . $e->getMessage());
        }
    }
}
