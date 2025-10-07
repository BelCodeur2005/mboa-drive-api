<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\CreateTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
/**
 * @OA\Tag(
 *     name="Trajets",
 *     description="Gestion des trajets de covoiturage"
 * )
 */
class TripController extends Controller
{
    /**
     * Recherche de trajets (route publique)
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = Trip::with(['driver', 'vehicle'])
                        ->available();

            // Filtrer par ville de départ
            if ($request->filled('from')) {
                $query->where('departure_city', 'like', '%' . $request->from . '%');
            }

            // Filtrer par ville d'arrivée
            if ($request->filled('to')) {
                $query->where('arrival_city', 'like', '%' . $request->to . '%');
            }

            // Filtrer par date
            if ($request->filled('date')) {
                $date = Carbon::parse($request->date);
                $query->whereDate('departure_date', $date);
            }

            // Filtrer par nombre de places
            if ($request->filled('seats')) {
                $query->where('available_seats', '>=', $request->seats);
            }

            // Filtrer par prix maximum
            if ($request->filled('max_price')) {
                $query->where('price_per_seat', '<=', $request->max_price);
            }

            // Trier par prix ou date
            $sortBy = $request->get('sort_by', 'departure_date');
            $sortOrder = $request->get('sort_order', 'asc');

            if (in_array($sortBy, ['departure_date', 'price_per_seat'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $trips = $query->paginate(10);

            return response()->json([
                'success' => true,
                'data' => TripResource::collection($trips),
                'pagination' => [
                    'current_page' => $trips->currentPage(),
                    'last_page' => $trips->lastPage(),
                    'per_page' => $trips->perPage(),
                    'total' => $trips->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche de trajets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un trajet spécifique
     */
    public function show(Trip $trip): JsonResponse
    {
        try {
            $trip->load(['driver', 'vehicle', 'stops', 'confirmedBookings.passenger']);

            return response()->json([
                'success' => true,
                'data' => new TripResource($trip)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du trajet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau trajet
     */
    public function store(CreateTripRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est conducteur
            if (!$user->isDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les conducteurs peuvent créer des trajets'
                ], 403);
            }

            // Vérifier que le véhicule appartient à l'utilisateur
            $vehicle = Vehicle::where('id', $request->vehicle_id)
                            ->where('user_id', $user->id)
                            ->where('is_active', true)
                            ->first();

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Véhicule introuvable ou inactif'
                ], 404);
            }

            DB::beginTransaction();

            $trip = Trip::create([
                'driver_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'departure_city' => $request->departure_city,
                'departure_address' => $request->departure_address,
                'departure_latitude' => $request->departure_latitude,
                'departure_longitude' => $request->departure_longitude,
                'arrival_city' => $request->arrival_city,
                'arrival_address' => $request->arrival_address,
                'arrival_latitude' => $request->arrival_latitude,
                'arrival_longitude' => $request->arrival_longitude,
                'departure_date' => $request->departure_date,
                'arrival_date' => $request->arrival_date,
                'available_seats' => $request->available_seats,
                'total_seats' => $request->available_seats,
                'price_per_seat' => $request->price_per_seat,
                'total_distance' => $request->total_distance,
                'estimated_duration' => $request->estimated_duration,
                'instant_booking' => $request->boolean('instant_booking', true),
                'smoking_allowed' => $request->boolean('smoking_allowed', false),
                'pets_allowed' => $request->boolean('pets_allowed', false),
                'max_2_in_back' => $request->get('max_2_in_back', 2),
                'additional_info' => $request->additional_info,
            ]);

            // Ajouter les points d'arrêt s'il y en a
            if ($request->filled('stops')) {
                foreach ($request->stops as $index => $stop) {
                    $trip->stops()->create([
                        'city' => $stop['city'],
                        'address' => $stop['address'],
                        'latitude' => $stop['latitude'] ?? null,
                        'longitude' => $stop['longitude'] ?? null,
                        'order' => $index + 1
                    ]);
                }
            }

            DB::commit();

            $trip->load(['driver', 'vehicle', 'stops']);

            return response()->json([
                'success' => true,
                'message' => 'Trajet créé avec succès',
                'data' => new TripResource($trip)
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du trajet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un trajet
     */
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est le propriétaire du trajet
            if ($trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce trajet'
                ], 403);
            }

            // Vérifier que le trajet peut encore être modifié
            if ($trip->status !== 'scheduled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce trajet ne peut plus être modifié'
                ], 422);
            }

            // Vérifier s'il y a des réservations confirmées
            if ($trip->confirmedBookings()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier un trajet avec des réservations confirmées'
                ], 422);
            }

            $trip->update($request->only([
                'departure_address', 'arrival_address',
                'departure_date', 'arrival_date',
                'available_seats', 'price_per_seat',
                'smoking_allowed', 'pets_allowed',
                'max_2_in_back', 'additional_info'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Trajet mis à jour avec succès',
                'data' => new TripResource($trip->fresh(['driver', 'vehicle', 'stops']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du trajet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un trajet
     */
    public function destroy(Request $request, Trip $trip): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est le propriétaire du trajet
            if ($trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à supprimer ce trajet'
                ], 403);
            }

            // Vérifier que le trajet peut être supprimé
            if ($trip->status === 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un trajet en cours'
                ], 422);
            }

            // Annuler d'abord le trajet pour gérer les réservations
            if ($trip->status === 'scheduled') {
                $trip->cancel('Trajet supprimé par le conducteur');
            }

            $trip->delete();

            return response()->json([
                'success' => true,
                'message' => 'Trajet supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du trajet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Démarrer un trajet
     */
    public function start(Request $request, Trip $trip): JsonResponse
    {
        try {
            $user = $request->user();

            if ($trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à démarrer ce trajet'
                ], 403);
            }

            if ($trip->status !== 'scheduled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce trajet ne peut pas être démarré'
                ], 422);
            }

            $trip->markAsStarted();

            return response()->json([
                'success' => true,
                'message' => 'Trajet démarré avec succès',
                'data' => new TripResource($trip->fresh(['driver', 'vehicle']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage du trajet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes trajets (conducteur)
     */
    public function myTrips(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Trip::with(['vehicle', 'confirmedBookings.passenger'])
                        ->byDriver($user->id);

            // Filtrer par statut
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $trips = $query->orderBy('departure_date', 'desc')->paginate(10);

            return response()->json([
                'success' => true,
                'data' => TripResource::collection($trips),
                'pagination' => [
                    'current_page' => $trips->currentPage(),
                    'last_page' => $trips->lastPage(),
                    'per_page' => $trips->perPage(),
                    'total' => $trips->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de vos trajets',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
