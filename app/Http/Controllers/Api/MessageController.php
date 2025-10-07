<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * Obtenir toutes les conversations de l'utilisateur
     */
    public function conversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Récupérer les derniers messages de chaque conversation
            $conversations = DB::select("
                SELECT
                    m.*,
                    u.id as other_user_id,
                    u.name as other_user_name,
                    u.profile_photo as other_user_photo,
                    u.average_rating as other_user_rating,
                    COUNT(unread.id) as unread_count
                FROM messages m
                INNER JOIN (
                    SELECT
                        CASE
                            WHEN sender_id = ? THEN recipient_id
                            ELSE sender_id
                        END as other_user_id,
                        MAX(created_at) as last_message_time
                    FROM messages
                    WHERE (sender_id = ? OR recipient_id = ?)
                      AND ((sender_id = ? AND is_deleted_by_sender = 0)
                           OR (recipient_id = ? AND is_deleted_by_recipient = 0))
                    GROUP BY other_user_id
                ) latest ON m.created_at = latest.last_message_time
                    AND (
                        (m.sender_id = ? AND m.recipient_id = latest.other_user_id)
                        OR (m.recipient_id = ? AND m.sender_id = latest.other_user_id)
                    )
                INNER JOIN users u ON u.id = latest.other_user_id
                LEFT JOIN messages unread ON (
                    unread.sender_id = latest.other_user_id
                    AND unread.recipient_id = ?
                    AND unread.is_read = 0
                    AND unread.is_deleted_by_recipient = 0
                )
                GROUP BY m.id, u.id
                ORDER BY m.created_at DESC
            ", array_fill(0, 8, $user->id));

            // Transformer en collection
            $formattedConversations = collect($conversations)->map(function ($conv) use ($user) {
                return [
                    'other_user' => [
                        'id' => $conv->other_user_id,
                        'name' => $conv->other_user_name,
                        'profile_photo_url' => $conv->other_user_photo ? asset('storage/' . $conv->other_user_photo) : null,
                        'average_rating' => $conv->other_user_rating,
                    ],
                    'last_message' => [
                        'id' => $conv->id,
                        'message' => $conv->message,
                        'type' => $conv->type,
                        'sender_id' => $conv->sender_id,
                        'is_from_me' => $conv->sender_id == $user->id,
                        'created_at' => $conv->created_at,
                        'created_ago' => \Carbon\Carbon::parse($conv->created_at)->diffForHumans(),
                    ],
                    'unread_count' => (int) $conv->unread_count,
                    'has_unread' => $conv->unread_count > 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedConversations,
                'total_unread' => $formattedConversations->sum('unread_count')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les messages d'une conversation avec un utilisateur
     */
    public function conversation(Request $request, $userId): JsonResponse
    {
        try {
            $currentUser = $request->user();
            $otherUser = User::findOrFail($userId);

            if ($currentUser->id == $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas avoir une conversation avec vous-même'
                ], 422);
            }

            // Récupérer les messages de la conversation
            $messages = Message::conversation($currentUser->id, $userId)
                              ->with(['sender', 'recipient'])
                              ->orderBy('created_at', 'asc')
                              ->paginate(50);

            // Marquer les messages reçus comme lus
            Message::where('sender_id', $userId)
                   ->where('recipient_id', $currentUser->id)
                   ->where('is_read', false)
                   ->update([
                       'is_read' => true,
                       'read_at' => now()
                   ]);

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'other_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'profile_photo_url' => $otherUser->profile_photo_url,
                    'average_rating' => $otherUser->average_rating,
                    'is_active' => $otherUser->last_active_at && $otherUser->last_active_at->gt(now()->subMinutes(5)),
                    'last_active_at' => $otherUser->last_active_at?->diffForHumans(),
                ],
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer un message
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'recipient_id' => 'required|exists:users,id|different:' . $request->user()->id,
                'message' => 'required|string|max:1000',
                'type' => 'sometimes|in:text,location,image,system',
                'booking_id' => 'sometimes|exists:bookings,id',
                'metadata' => 'sometimes|array',
                'metadata.latitude' => 'required_if:type,location|numeric|between:-90,90',
                'metadata.longitude' => 'required_if:type,location|numeric|between:-180,180',
                'metadata.address' => 'sometimes|string|max:255',
            ]);

            $user = $request->user();
            $recipientId = $request->recipient_id;

            // Vérifier que le destinataire existe et est actif
            $recipient = User::where('id', $recipientId)->where('is_active', true)->first();
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Destinataire introuvable ou inactif'
                ], 404);
            }

            // Si un booking_id est fourni, vérifier que l'utilisateur y est impliqué
            if ($request->filled('booking_id')) {
                $booking = \App\Models\Booking::with('trip')->findOrFail($request->booking_id);
                $userInvolved = $booking->passenger_id === $user->id ||
                               $booking->trip->driver_id === $user->id;

                if (!$userInvolved) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vous n\'êtes pas autorisé à envoyer des messages pour cette réservation'
                    ], 403);
                }

                // Vérifier que le destinataire est aussi impliqué
                $recipientInvolved = $booking->passenger_id === $recipientId ||
                                   $booking->trip->driver_id === $recipientId;

                if (!$recipientInvolved) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le destinataire n\'est pas impliqué dans cette réservation'
                    ], 403);
                }
            }

            // Créer le message
            $message = Message::create([
                'sender_id' => $user->id,
                'recipient_id' => $recipientId,
                'booking_id' => $request->booking_id,
                'message' => $request->message,
                'type' => $request->get('type', 'text'),
                'metadata' => $request->metadata,
            ]);

            $message->load(['sender', 'recipient']);

            // TODO: Envoyer une notification push au destinataire
            // TODO: Émettre l'événement WebSocket pour le chat temps réel

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès',
                'data' => new MessageResource($message)
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un message comme lu
     */
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est le destinataire
            if ($message->recipient_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez marquer comme lu que les messages que vous avez reçus'
                ], 403);
            }

            $message->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Message marqué comme lu',
                'data' => new MessageResource($message->fresh(['sender', 'recipient']))
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
     * Nombre de messages non lus
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $count = Message::where('recipient_id', $user->id)
                           ->where('is_read', false)
                           ->where('is_deleted_by_recipient', false)
                           ->count();

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
     * Supprimer un message
     */
    public function delete(Request $request, Message $message): JsonResponse
    {
        try {
            $user = $request->user();

            if ($message->sender_id === $user->id) {
                $message->deleteForSender();
                $messageText = 'Message supprimé de votre côté';
            } elseif ($message->recipient_id === $user->id) {
                $message->deleteForRecipient();
                $messageText = 'Message supprimé de votre côté';
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer ce message'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => $messageText
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher dans les messages
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:100',
                'user_id' => 'sometimes|exists:users,id'
            ]);

            $user = $request->user();

            $query = Message::forUser($user->id)
                           ->with(['sender', 'recipient'])
                           ->where('message', 'like', '%' . $request->query . '%')
                           ->where('type', 'text');

            // Filtrer par utilisateur spécifique si fourni
            if ($request->filled('user_id')) {
                $query->betweenUsers($user->id, $request->user_id);
            }

            $messages = $query->orderBy('created_at', 'desc')
                             ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'query' => $request->query,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
