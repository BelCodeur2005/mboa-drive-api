<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


/**
 * @OA\Tag(
 *     name="Authentification",
 *     description="Endpoints pour l'authentification des utilisateurs"
 * )
 */
class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     tags={"Authentification"},
     *     summary="Inscription d'un nouvel utilisateur",
     *     description="Créer un nouveau compte utilisateur avec les informations requises",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","phone","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="Jean Mballa"),
     *             @OA\Property(property="email", type="string", format="email", example="jean@example.com"),
     *             @OA\Property(property="phone", type="string", example="651234567"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="motdepasse123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="motdepasse123"),
     *             @OA\Property(property="user_type", type="string", enum={"passenger","driver","both"}, example="passenger"),
     *             @OA\Property(property="city", type="string", example="Douala"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-15"),
     *             @OA\Property(property="gender", type="string", enum={"male","female","other"}, example="male")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscription réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inscription réussie"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource"),
     *                 @OA\Property(property="token", type="string", example="1|abcdef123456..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur de validation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'user_type' => $request->user_type ?? 'passenger',
                'city' => $request->city,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
            ]);

            // Créer le token d'authentification
            $token = $user->createToken('auth-token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connexion utilisateur
     */
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Authentification"},
     *     summary="Connexion utilisateur",
     *     description="Authentifier un utilisateur avec email/téléphone et mot de passe",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login","password"},
     *             @OA\Property(property="login", type="string", description="Email ou numéro de téléphone", example="jean@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="motdepasse123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connexion réussie"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource"),
     *                 @OA\Property(property="token", type="string", example="2|fedcba654321..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Identifiants incorrects",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur de validation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Compte désactivé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Votre compte a été désactivé")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Chercher l'utilisateur par email ou téléphone
            $user = User::where('email', $request->login)
                       ->orWhere('phone', $request->login)
                       ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'login' => ['Les informations d\'identification fournies sont incorrectes.'],
                ]);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé. Contactez le support.'
                ], 403);
            }

            // Supprimer les anciens tokens
            $user->tokens()->delete();

            // Créer un nouveau token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Mettre à jour la dernière activité
            $user->markAsActive();

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion utilisateur
     */
    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Authentification"},
     *     summary="Déconnexion utilisateur",
     *     description="Supprimer le token d'authentification actuel",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Supprimer le token actuel
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Authentification"},
     *     summary="Profil utilisateur connecté",
     *     description="Récupérer les informations de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/UserResource")
     *         )
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->markAsActive();

            return response()->json([
                'success' => true,
                'data' => new UserResource($user->load(['vehicles', 'primaryVehicle']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérification du numéro de téléphone par OTP
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6'
        ]);

        try {
            // TODO: Implémenter la vérification OTP réelle avec un service SMS
            // Pour l'instant, on accepte le code "123456" pour les tests

            if ($request->otp !== '123456') {
                return response()->json([
                    'success' => false,
                    'message' => 'Code OTP invalide'
                ], 422);
            }

            $user = $request->user();
            $user->update([
                'phone_verified' => true,
                'phone' => $request->phone
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Numéro de téléphone vérifié avec succès',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer un code OTP pour vérification
     */
    public function sendOTP(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/'
        ]);

        try {
            // TODO: Implémenter l'envoi d'OTP réel via SMS
            // Pour les tests, on simule l'envoi

            // Générer un code OTP aléatoire (à stocker en cache/DB en production)
            $otp = rand(100000, 999999);

            // En production, stocker l'OTP avec une expiration
            // Cache::put("otp_{$request->phone}", $otp, now()->addMinutes(5));

            // En production, envoyer le SMS via un service comme Twilio, Nexmo, etc.

            return response()->json([
                'success' => true,
                'message' => 'Code OTP envoyé avec succès',
                'debug' => [
                    'otp' => $otp, // À supprimer en production
                    'phone' => $request->phone
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialisation du mot de passe
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);

        try {
            // Vérifier l'OTP (simulation pour les tests)
            if ($request->otp !== '123456') {
                return response()->json([
                    'success' => false,
                    'message' => 'Code OTP invalide'
                ], 422);
            }

            $user = User::where('phone', $request->phone)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun utilisateur trouvé avec ce numéro de téléphone'
                ], 404);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Supprimer tous les tokens existants pour forcer une nouvelle connexion
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
