<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
/**
 * @OA\Tag(
 *     name="Réservations",
 *     description="Gestion des réservations de trajets"
 * )
 */
class BookingController extends Controller
{
    /**
     * Créer une nouvelle réservation
     */
    /**
     * @OA\Post(
     *     path="/api/v1/bookings",
     *     tags={"Réservations"},
     *     summary="Créer une réservation",
     *     description="Réserver une ou plusieurs places sur un trajet existant (passagers uniquement)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"trip_id","seats_booked"},
     *             @OA\Property(property="trip_id", type="integer", description="ID du trajet à réserver", example=1),
     *             @OA\Property(property="seats_booked", type="integer", minimum=1, maximum=8, description="Nombre de places à réserver", example=2),
     *             @OA\Property(property="passenger_message", type="string", nullable=true, maxLength=500, description="Message pour le conducteur", example="Bonjour, je serai à l'heure avec un petit bagage"),
     *             @OA\Property(property="pickup_address", type="string", nullable=true, description="Adresse de prise en charge personnalisée", example="Devant la pharmacie du carrefour Deido"),
     *             @OA\Property(property="pickup_latitude", type="number", format="float", nullable=true, description="Latitude du point de prise en charge", example=4.0614),
     *             @OA\Property(property="pickup_longitude", type="number", format="float", nullable=true, description="Longitude du point de prise en charge", example=9.7847),
     *             @OA\Property(property="dropoff_address", type="string", nullable=true, description="Adresse de dépose personnalisée", example="Université de Yaoundé I - Entrée principale"),
     *             @OA\Property(property="dropoff_latitude", type="number", format="float", nullable=true, description="Latitude du point de dépose", example=3.8667),
     *             @OA\Property(property="dropoff_longitude", type="number", format="float", nullable=true, description="Longitude du point de dépose", example=11.5167)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Réservation créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Réservation confirmée automatiquement"),
     *             @OA\Property(property="data", ref="#/components/schemas/BookingResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - Seuls les passagers peuvent réserver",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Seuls les passagers peuvent effectuer des réservations")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreurs de logique métier",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Examples(example="own_trip", summary="Propre trajet", value={"success": false, "message": "Vous ne pouvez pas réserver votre propre trajet"}),
     *             @OA\Examples(example="not_available", summary="Trajet non disponible", value={"success": false, "message": "Ce trajet n'est plus disponible pour réservation"}),
     *             @OA\Examples(example="not_enough_seats", summary="Pas assez de places", value={"success": false, "message": "Pas assez de places disponibles"}),
     *             @OA\Examples(example="already_booked", summary="Déjà réservé", value={"success": false, "message": "Vous avez déjà une réservation en cours pour ce trajet"})
     *         )
     *     )
     * )
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est un passager
            if (!$user->isPassenger()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les passagers peuvent effectuer des réservations'
                ], 403);
            }

            $trip = Trip::with(['driver', 'vehicle'])->findOrFail($request->trip_id);

            // Vérifications de base
            if ($trip->driver_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas réserver votre propre trajet'
                ], 422);
            }

            if ($trip->status !== 'scheduled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce trajet n\'est plus disponible pour réservation'
                ], 422);
            }

            if ($trip->departure_date->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de réserver un trajet déjà passé'
                ], 422);
            }

            if ($trip->available_seats < $request->seats_booked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pas assez de places disponibles'
                ], 422);
            }

            // Vérifier si l'utilisateur n'a pas déjà réservé ce trajet
            $existingBooking = Booking::where('trip_id', $trip->id)
                                    ->where('passenger_id', $user->id)
                                    ->whereIn('status', ['pending', 'confirmed'])
                                    ->first();

            if ($existingBooking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà une réservation en cours pour ce trajet'
                ], 422);
            }

            DB::beginTransaction();

            // Calculer le montant total
            $totalAmount = $trip->price_per_seat * $request->seats_booked;

            // Créer la réservation
            $booking = Booking::create([
                'trip_id' => $trip->id,
                'passenger_id' => $user->id,
                'seats_booked' => $request->seats_booked,
                'total_amount' => $totalAmount,
                'passenger_message' => $request->passenger_message,
                'pickup_address' => $request->pickup_address,
                'pickup_latitude' => $request->pickup_latitude,
                'pickup_longitude' => $request->pickup_longitude,
                'dropoff_address' => $request->dropoff_address,
                'dropoff_latitude' => $request->dropoff_latitude,
                'dropoff_longitude' => $request->dropoff_longitude,
                'status' => $trip->instant_booking ? 'confirmed' : 'pending'
            ]);

            // Si réservation instantanée, décrémenter immédiatement les places disponibles
            if ($trip->instant_booking) {
                $booking->confirm();
            }

            DB::commit();

            // Charger les relations
            $booking->load(['trip.driver', 'trip.vehicle', 'passenger']);

            // TODO: Envoyer une notification au conducteur

            return response()->json([
                'success' => true,
                'message' => $trip->instant_booking ? 'Réservation confirmée automatiquement' : 'Demande de réservation envoyée au conducteur',
                'data' => new BookingResource($booking)
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes réservations (passager)
     */

    /**
     * @OA\Get(
     *     path="/api/v1/bookings/my-bookings",
     *     tags={"Réservations"},
     *     summary="Mes réservations",
     *     description="Récupérer toutes les réservations du passager connecté avec options de filtrage",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut de réservation",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending","confirmed","cancelled","completed","no_show"},
     *             example="confirmed"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Filtrer par période temporelle",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"upcoming","past","today"},
     *             example="upcoming"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des réservations du passager",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/BookingResource")
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25)
     *             )
     *         )
     *     )
     * )
     */
    public function myBookings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Booking::with(['trip.driver', 'trip.vehicle'])
                           ->byPassenger($user->id);

            // Filtrer par statut
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filtrer par période
            if ($request->filled('period')) {
                $period = $request->period;
                match($period) {
                    'upcoming' => $query->whereHas('trip', function ($q) {
                        $q->where('departure_date', '>', now());
                    }),
                    'past' => $query->whereHas('trip', function ($q) {
                        $q->where('departure_date', '<', now());
                    }),
                    'today' => $query->whereHas('trip', function ($q) {
                        $q->whereDate('departure_date', today());
                    }),
                    default => null
                };
            }

            $bookings = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de vos réservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réservations pour un trajet (conducteur)
     */
    public function tripBookings(Request $request, Trip $trip): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est le conducteur du trajet
            if ($trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à voir ces réservations'
                ], 403);
            }

            $bookings = $trip->bookings()->with(['passenger'])->get();

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des réservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmer une réservation (conducteur)
     */
    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est le conducteur
            if ($booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à confirmer cette réservation'
                ], 403);
            }

            if ($booking->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne peut pas être confirmée'
                ], 422);
            }

            // Vérifier qu'il y a encore assez de places
            if ($booking->trip->available_seats < $booking->seats_booked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plus assez de places disponibles'
                ], 422);
            }

            $booking->confirm();

            return response()->json([
                'success' => true,
                'message' => 'Réservation confirmée avec succès',
                'data' => new BookingResource($booking->fresh(['trip.driver', 'trip.vehicle', 'passenger']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeter une réservation (conducteur)
     */
    public function reject(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            if ($booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à rejeter cette réservation'
                ], 403);
            }

            if ($booking->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne peut pas être rejetée'
                ], 422);
            }

            $reason = $request->get('reason', 'Refusé par le conducteur');
            $booking->cancel($reason);

            return response()->json([
                'success' => true,
                'message' => 'Réservation rejetée',
                'data' => new BookingResource($booking->fresh(['passenger']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler une réservation (passager)
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            if ($booking->passenger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à annuler cette réservation'
                ], 403);
            }

            if (!$booking->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne peut plus être annulée'
                ], 422);
            }

            $reason = $request->get('reason', 'Annulé par le passager');
            $booking->cancel($reason);

            return response()->json([
                'success' => true,
                'message' => 'Réservation annulée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un passager comme pris en charge (conducteur)
     */
    public function markPickup(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            if ($booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à effectuer cette action'
                ], 403);
            }

            if ($booking->status !== 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation n\'est pas confirmée'
                ], 422);
            }

            if ($booking->picked_up_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce passager est déjà marqué comme pris en charge'
                ], 422);
            }

            $booking->markAsPickedUp();

            return response()->json([
                'success' => true,
                'message' => 'Passager marqué comme pris en charge',
                'data' => new BookingResource($booking->fresh(['passenger']))
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
     * Marquer un passager comme déposé (conducteur)
     */
    public function markDropoff(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            if ($booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à effectuer cette action'
                ], 403);
            }

            if (!$booking->picked_up_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le passager doit d\'abord être marqué comme pris en charge'
                ], 422);
            }

            if ($booking->dropped_off_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce passager est déjà marqué comme déposé'
                ], 422);
            }

            $booking->markAsDroppedOff();

            return response()->json([
                'success' => true,
                'message' => 'Passager marqué comme déposé',
                'data' => new BookingResource($booking->fresh(['passenger']))
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
     * Afficher les détails d'une réservation
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est impliqué dans cette réservation
            if ($booking->passenger_id !== $user->id && $booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à voir cette réservation'
                ], 403);
            }

            $booking->load(['trip.driver', 'trip.vehicle', 'passenger', 'payments']);

            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historique des réservations
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Booking::with(['trip.driver', 'trip.vehicle'])
                           ->where(function ($q) use ($user) {
                               $q->where('passenger_id', $user->id)
                                 ->orWhereHas('trip', function ($subQ) use ($user) {
                                     $subQ->where('driver_id', $user->id);
                                 });
                           })
                           ->whereIn('status', ['completed', 'cancelled']);

            $bookings = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => BookingResource::collection($bookings),
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
