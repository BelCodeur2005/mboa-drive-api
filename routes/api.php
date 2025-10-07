<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MessageController;

/*
|--------------------------------------------------------------------------
| API Routes pour Mboa Drive
|--------------------------------------------------------------------------
*/

// Routes publiques (sans authentification)
Route::prefix('v1')->group(function () {

    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/send-otp', [AuthController::class, 'sendOTP']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Recherche de trajets (accessible sans connexion)
    Route::get('/trips/search', [TripController::class, 'search']);
    Route::get('/trips/{trip}', [TripController::class, 'show']);

    // Informations générales
    Route::get('/cities', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'popular_cities' => [
                    'Douala', 'Yaoundé', 'Bafoussam', 'Bamenda',
                    'Garoua', 'Maroua', 'Ngaoundéré', 'Bertoua'
                ]
            ]
        ]);
    });
});

// Routes protégées (avec authentification)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
    });

    // Profil utilisateur
    Route::prefix('profile')->group(function () {
        Route::put('/', [UserController::class, 'update']);
        Route::post('/photo', [UserController::class, 'uploadPhoto']);
        Route::delete('/photo', [UserController::class, 'deletePhoto']);
        Route::put('/preferences', [UserController::class, 'updatePreferences']);
        // Enregistrer le token FCM
        Route::post('/fcm-token', [UserController::class, 'updateFCMToken']);

        // Activer/désactiver les notifications push
        Route::post('/push-notifications', [UserController::class, 'togglePushNotifications']);
    });

    // Véhicules (pour les conducteurs)
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/{vehicle}', [VehicleController::class, 'show']);
        Route::put('/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy']);
        Route::post('/{vehicle}/photos', [VehicleController::class, 'uploadPhotos']);
        Route::delete('/{vehicle}/photos/{photoIndex}', [VehicleController::class, 'deletePhoto']);
        Route::put('/{vehicle}/activate', [VehicleController::class, 'activate']);
    });

    // Trajets
    Route::prefix('trips')->group(function () {
        // Gestion des trajets par le conducteur
        Route::get('/my-trips', [TripController::class, 'myTrips']);
        Route::post('/', [TripController::class, 'store']);
        Route::put('/{trip}', [TripController::class, 'update']);
        Route::delete('/{trip}', [TripController::class, 'destroy']);
        Route::post('/{trip}/start', [TripController::class, 'start']);
        Route::post('/{trip}/complete', [TripController::class, 'complete']);
        Route::post('/{trip}/cancel', [TripController::class, 'cancel']);

        // Gestion des points d'arrêt
        Route::post('/{trip}/stops', [TripController::class, 'addStop']);
        Route::delete('/{trip}/stops/{stop}', [TripController::class, 'removeStop']);

        // Historique des trajets
        Route::get('/history', [TripController::class, 'history']);
    });

    // Réservations
    Route::prefix('bookings')->group(function () {
        // Pour les passagers
        Route::get('/my-bookings', [BookingController::class, 'myBookings']);
        Route::post('/', [BookingController::class, 'store']);
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel']);

        // Pour les conducteurs
        Route::get('/trip/{trip}', [BookingController::class, 'tripBookings']);
        Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::post('/{booking}/reject', [BookingController::class, 'reject']);
        Route::post('/{booking}/pickup', [BookingController::class, 'markPickup']);
        Route::post('/{booking}/dropoff', [BookingController::class, 'markDropoff']);

        // Détails d'une réservation
        Route::get('/{booking}', [BookingController::class, 'show']);

        // Historique des réservations
        Route::get('/history', [BookingController::class, 'history']);
    });

    // Évaluations
    Route::prefix('ratings')->group(function () {
        Route::post('/', [RatingController::class, 'store']);
        Route::get('/my-ratings', [RatingController::class, 'myRatings']);
        Route::get('/received', [RatingController::class, 'receivedRatings']);
        Route::get('/user/{user}', [RatingController::class, 'userRatings']);
    });

    // Paiements
    Route::prefix('payments')->group(function () {
        Route::post('/initialize', [PaymentController::class, 'initialize']);
        Route::post('/mobile-money', [PaymentController::class, 'mobileMoney']);
        Route::post('/cash/confirm', [PaymentController::class, 'confirmCashPayment']);
        Route::get('/history', [PaymentController::class, 'history']);
        Route::get('/{payment}/status', [PaymentController::class, 'status']);
    });

    // Messages/Chat
    Route::prefix('messages')->group(function () {
        Route::get('/conversations', [MessageController::class, 'conversations']);
        Route::get('/conversation/{userId}', [MessageController::class, 'conversation']);
        Route::post('/send', [MessageController::class, 'send']);
        Route::put('/{message}/read', [MessageController::class, 'markAsRead']);
        Route::get('/unread-count', [MessageController::class, 'unreadCount']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [UserController::class, 'notifications']);
        Route::put('/{notification}/read', [UserController::class, 'markNotificationAsRead']);
        Route::post('/mark-all-read', [UserController::class, 'markAllNotificationsAsRead']);
        Route::get('/unread-count', [UserController::class, 'unreadNotificationsCount']);
    });

    // Recherche et découverte
    Route::prefix('search')->group(function () {
        Route::get('/users', [UserController::class, 'searchUsers']);
        Route::get('/recent-routes', [TripController::class, 'recentRoutes']);
        Route::get('/popular-destinations', [TripController::class, 'popularDestinations']);
    });

    // Statistiques et tableaux de bord
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [UserController::class, 'dashboardStats']);
        Route::get('/driver', [TripController::class, 'driverDashboard']);
        Route::get('/passenger', [BookingController::class, 'passengerDashboard']);
    });

    // Signalements et support
    Route::prefix('support')->group(function () {
        Route::post('/report', [UserController::class, 'reportUser']);
        Route::post('/feedback', [UserController::class, 'submitFeedback']);
        Route::get('/help', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'faq' => [
                        [
                            'question' => 'Comment publier un trajet ?',
                            'answer' => 'Rendez-vous dans la section "Mes trajets" et cliquez sur "Publier un trajet".'
                        ],
                        [
                            'question' => 'Comment réserver une place ?',
                            'answer' => 'Recherchez un trajet, sélectionnez celui qui vous convient et cliquez sur "Réserver".'
                        ],
                        [
                            'question' => 'Comment fonctionne le paiement ?',
                            'answer' => 'Vous pouvez payer par Mobile Money (MTN/Orange) ou en espèces directement au conducteur.'
                        ]
                    ],
                    'contact' => [
                        'phone' => '+237 651 712 856',
                        'email' => 'support@mboadrive.com',
                        'hours' => '24h/24 7j/7'
                    ]
                ]
            ]);
        });
    });
});
