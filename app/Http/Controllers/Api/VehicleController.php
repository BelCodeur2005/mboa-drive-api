<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\CreateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    /**
     * Liste des véhicules de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->isDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les conducteurs peuvent avoir des véhicules'
                ], 403);
            }

            $vehicles = $user->vehicles()->orderBy('is_active', 'desc')->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => VehicleResource::collection($vehicles)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des véhicules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau véhicule
     */
    public function store(CreateVehicleRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->isDriver()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les conducteurs peuvent ajouter des véhicules'
                ], 403);
            }

            // Vérifier si l'utilisateur a déjà 3 véhicules (limite)
            $vehicleCount = $user->vehicles()->count();
            if ($vehicleCount >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas avoir plus de 3 véhicules'
                ], 422);
            }

            // Vérifier l'unicité de la plaque d'immatriculation
            $existingVehicle = Vehicle::where('license_plate', $request->license_plate)->first();
            if ($existingVehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette plaque d\'immatriculation est déjà enregistrée'
                ], 422);
            }

            $vehicle = Vehicle::create([
                'user_id' => $user->id,
                'brand' => $request->brand,
                'model' => $request->model,
                'color' => $request->color,
                'license_plate' => strtoupper($request->license_plate),
                'year' => $request->year,
                'seats' => $request->seats,
                'fuel_type' => $request->fuel_type,
                'air_conditioning' => $request->boolean('air_conditioning', false),
                'description' => $request->description,
                'is_active' => $vehicleCount === 0 // Premier véhicule = actif par défaut
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Véhicule ajouté avec succès',
                'data' => new VehicleResource($vehicle)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du véhicule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les détails d'un véhicule
     */
    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();

            // Seul le propriétaire peut voir les détails complets
            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à voir ce véhicule'
                ], 403);
            }

            $vehicle->load(['trips' => function ($query) {
                $query->where('status', 'completed')->take(10);
            }]);

            return response()->json([
                'success' => true,
                'data' => new VehicleResource($vehicle)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du véhicule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un véhicule
     */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();

            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce véhicule'
                ], 403);
            }

            // Validation
            $request->validate([
                'brand' => 'sometimes|string|max:50',
                'model' => 'sometimes|string|max:50',
                'color' => 'sometimes|string|max:30',
                'license_plate' => 'sometimes|string|max:20|unique:vehicles,license_plate,' . $vehicle->id,
                'year' => 'sometimes|integer|min:1990|max:' . (date('Y') + 1),
                'seats' => 'sometimes|integer|min:2|max:9',
                'fuel_type' => 'sometimes|in:petrol,diesel,hybrid,electric',
                'air_conditioning' => 'sometimes|boolean',
                'description' => 'sometimes|string|max:500'
            ]);

            $updateData = $request->only([
                'brand', 'model', 'color', 'year', 'seats',
                'fuel_type', 'description'
            ]);

            if ($request->has('license_plate')) {
                $updateData['license_plate'] = strtoupper($request->license_plate);
            }

            if ($request->has('air_conditioning')) {
                $updateData['air_conditioning'] = $request->boolean('air_conditioning');
            }

            $vehicle->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Véhicule mis à jour avec succès',
                'data' => new VehicleResource($vehicle->fresh())
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
                'message' => 'Erreur lors de la mise à jour du véhicule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un véhicule
     */
    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();

            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à supprimer ce véhicule'
                ], 403);
            }

            // Vérifier qu'il n'y a pas de trajets actifs avec ce véhicule
            $activeTrips = $vehicle->trips()
                                  ->whereIn('status', ['scheduled', 'in_progress'])
                                  ->count();

            if ($activeTrips > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un véhicule avec des trajets actifs'
                ], 422);
            }

            // Supprimer les photos du véhicule
            if ($vehicle->photos) {
                foreach ($vehicle->photos as $photo) {
                    Storage::disk('public')->delete($photo);
                }
            }

            $vehicle->delete();

            return response()->json([
                'success' => true,
                'message' => 'Véhicule supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du véhicule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload des photos du véhicule
     */
    public function uploadPhotos(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();

            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce véhicule'
                ], 403);
            }

            $request->validate([
                'photos' => 'required|array|max:5',
                'photos.*' => 'image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $photos = $vehicle->photos ?? [];
            $uploadedPhotos = [];

            foreach ($request->file('photos') as $photo) {
                if (count($photos) >= 5) {
                    break; // Maximum 5 photos
                }

                $filename = 'vehicles/' . $vehicle->id . '/' . Str::uuid() . '.' . $photo->getClientOriginalExtension();
                $path = $photo->storeAs('public', $filename);

                if ($path) {
                    $photos[] = $filename;
                    $uploadedPhotos[] = [
                        'filename' => $filename,
                        'url' => asset('storage/' . $filename)
                    ];
                }
            }

            $vehicle->update(['photos' => $photos]);

            return response()->json([
                'success' => true,
                'message' => 'Photos ajoutées avec succès',
                'data' => [
                    'uploaded_photos' => $uploadedPhotos,
                    'total_photos' => count($photos),
                    'vehicle' => new VehicleResource($vehicle->fresh())
                ]
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
                'message' => 'Erreur lors de l\'upload des photos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une photo du véhicule
     */
    public function deletePhoto(Request $request, Vehicle $vehicle, int $photoIndex): JsonResponse
    {
        try {
            $user = $request->user();

            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce véhicule'
                ], 403);
            }

            $photos = $vehicle->photos ?? [];

            if (!isset($photos[$photoIndex])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo non trouvée'
                ], 404);
            }

            // Supprimer le fichier
            Storage::disk('public')->delete($photos[$photoIndex]);

            // Retirer la photo du tableau
            array_splice($photos, $photoIndex, 1);

            $vehicle->update(['photos' => array_values($photos)]);

            return response()->json([
                'success' => true,
                'message' => 'Photo supprimée avec succès',
                'data' => new VehicleResource($vehicle->fresh())
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
     * Activer/désactiver un véhicule
     */
    public function activate(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();

            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce véhicule'
                ], 403);
            }

            $activate = $request->boolean('active', true);

            if ($activate) {
                // Désactiver tous les autres véhicules de l'utilisateur
                $user->vehicles()->where('id', '!=', $vehicle->id)->update(['is_active' => false]);
            }

            $vehicle->update(['is_active' => $activate]);

            $message = $activate ? 'Véhicule activé avec succès' : 'Véhicule désactivé avec succès';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new VehicleResource($vehicle->fresh())
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du véhicule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
