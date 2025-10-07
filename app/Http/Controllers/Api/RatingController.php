<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RatingController extends Controller
{
    /**
     * Donner une évaluation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500'
            ]);

            $user = $request->user();
            $booking = Booking::with(['trip.driver', 'passenger'])->findOrFail($request->booking_id);

            // Vérifier que l'utilisateur est impliqué dans cette réservation
            if ($booking->passenger_id !== $user->id && $booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à évaluer cette réservation'
                ], 403);
            }

            // Vérifier que la réservation est terminée
            if ($booking->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez évaluer que les trajets terminés'
                ], 422);
            }

            // Déterminer qui évalue qui
            $ratedUserId = $booking->passenger_id === $user->id
                ? $booking->trip->driver_id
                : $booking->passenger_id;

            $type = $booking->passenger_id === $user->id
                ? 'passenger_to_driver'
                : 'driver_to_passenger';

            // Vérifier qu'une évaluation n'existe pas déjà
            $existingRating = Rating::where('booking_id', $booking->id)
                                   ->where('rater_id', $user->id)
                                   ->where('rated_id', $ratedUserId)
                                   ->first();

            if ($existingRating) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà évalué cette réservation'
                ], 422);
            }

            // Créer l'évaluation
            $rating = Rating::create([
                'booking_id' => $booking->id,
                'rater_id' => $user->id,
                'rated_id' => $ratedUserId,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'type' => $type
            ]);

            $rating->load(['rater', 'rated', 'booking']);

            return response()->json([
                'success' => true,
                'message' => 'Évaluation enregistrée avec succès',
                'data' => new RatingResource($rating)
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
                'message' => 'Erreur lors de l\'enregistrement de l\'évaluation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Évaluations données par l'utilisateur connecté
     */
    public function myRatings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $ratings = Rating::with(['rated', 'booking.trip'])
                            ->where('rater_id', $user->id)
                            ->orderBy('created_at', 'desc')
                            ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => RatingResource::collection($ratings),
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des évaluations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Évaluations reçues par l'utilisateur connecté
     */
    public function receivedRatings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $ratings = Rating::with(['rater', 'booking.trip'])
                            ->where('rated_id', $user->id)
                            ->orderBy('created_at', 'desc')
                            ->paginate(15);

            // Calculer les statistiques
            $stats = [
                'average_rating' => $user->average_rating,
                'total_ratings' => $user->total_ratings,
                'rating_breakdown' => [
                    '5_stars' => Rating::where('rated_id', $user->id)->where('rating', 5)->count(),
                    '4_stars' => Rating::where('rated_id', $user->id)->where('rating', 4)->count(),
                    '3_stars' => Rating::where('rated_id', $user->id)->where('rating', 3)->count(),
                    '2_stars' => Rating::where('rated_id', $user->id)->where('rating', 2)->count(),
                    '1_star' => Rating::where('rated_id', $user->id)->where('rating', 1)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => RatingResource::collection($ratings),
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des évaluations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Évaluations d'un utilisateur spécifique (publique)
     */
    public function userRatings(Request $request, $userId): JsonResponse
    {
        try {
            $ratings = Rating::with(['rater', 'booking.trip'])
                            ->where('rated_id', $userId)
                            ->where('rating', '>=', 3) // Afficher seulement les évaluations moyennes et bonnes
                            ->orderBy('created_at', 'desc')
                            ->paginate(10);

            $user = \App\Models\User::findOrFail($userId);

            $stats = [
                'average_rating' => $user->average_rating,
                'total_ratings' => $user->total_ratings,
                'recent_ratings_count' => Rating::where('rated_id', $userId)
                                               ->where('created_at', '>=', now()->subMonths(3))
                                               ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => RatingResource::collection($ratings),
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des évaluations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques détaillées des évaluations
     */
    public function ratingStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = [
                'given_ratings' => [
                    'total' => Rating::where('rater_id', $user->id)->count(),
                    'average_given' => Rating::where('rater_id', $user->id)->avg('rating') ?? 0,
                    'by_type' => [
                        'to_drivers' => Rating::where('rater_id', $user->id)->where('type', 'passenger_to_driver')->count(),
                        'to_passengers' => Rating::where('rater_id', $user->id)->where('type', 'driver_to_passenger')->count(),
                    ]
                ],
                'received_ratings' => [
                    'total' => $user->total_ratings,
                    'average' => $user->average_rating,
                    'recent_trend' => $this->calculateRatingTrend($user->id),
                    'by_type' => [
                        'as_driver' => Rating::where('rated_id', $user->id)->where('type', 'passenger_to_driver')->count(),
                        'as_passenger' => Rating::where('rated_id', $user->id)->where('type', 'driver_to_passenger')->count(),
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réservations en attente d'évaluation
     */
    public function pendingRatings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Trouver les réservations terminées sans évaluation
            $bookings = \App\Models\Booking::with(['trip.driver', 'passenger'])
                ->where(function ($query) use ($user) {
                    $query->where('passenger_id', $user->id)
                          ->orWhereHas('trip', function ($q) use ($user) {
                              $q->where('driver_id', $user->id);
                          });
                })
                ->where('status', 'completed')
                ->whereDoesntHave('ratings', function ($query) use ($user) {
                    $query->where('rater_id', $user->id);
                })
                ->where('updated_at', '>=', now()->subDays(30)) // Seulement les 30 derniers jours
                ->orderBy('updated_at', 'desc')
                ->get();

            $pendingRatings = $bookings->map(function ($booking) use ($user) {
                $isDriver = $booking->trip->driver_id === $user->id;
                $targetUser = $isDriver ? $booking->passenger : $booking->trip->driver;

                return [
                    'booking_id' => $booking->id,
                    'trip' => [
                        'id' => $booking->trip->id,
                        'departure_city' => $booking->trip->departure_city,
                        'arrival_city' => $booking->trip->arrival_city,
                        'departure_date' => $booking->trip->departure_date->toISOString(),
                    ],
                    'target_user' => [
                        'id' => $targetUser->id,
                        'name' => $targetUser->name,
                        'profile_photo_url' => $targetUser->profile_photo_url,
                    ],
                    'role' => $isDriver ? 'driver' : 'passenger',
                    'completed_at' => $booking->updated_at->toISOString(),
                    'days_ago' => $booking->updated_at->diffInDays(now()),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $pendingRatings,
                'count' => $pendingRatings->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des évaluations en attente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer la tendance des évaluations (amélioration ou dégradation)
     */
    private function calculateRatingTrend(int $userId): array
    {
        $recentRatings = Rating::where('rated_id', $userId)
                              ->where('created_at', '>=', now()->subMonths(3))
                              ->avg('rating') ?? 0;

        $olderRatings = Rating::where('rated_id', $userId)
                             ->where('created_at', '<', now()->subMonths(3))
                             ->avg('rating') ?? 0;

        $trend = 'stable';
        $difference = 0;

        if ($recentRatings > 0 && $olderRatings > 0) {
            $difference = round($recentRatings - $olderRatings, 2);

            if ($difference > 0.2) {
                $trend = 'improving';
            } elseif ($difference < -0.2) {
                $trend = 'declining';
            }
        }

        return [
            'trend' => $trend,
            'difference' => $difference,
            'recent_average' => round($recentRatings, 2),
            'previous_average' => round($olderRatings, 2)
        ];
    }
}
