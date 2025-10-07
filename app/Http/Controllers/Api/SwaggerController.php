<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Mboa Drive API",
 *     version="1.0.0",
 *     description="API pour l'application de covoiturage Mboa Drive - Cameroun",
 *     @OA\Contact(
 *         email="support@mboadrive.com",
 *         name="Support Mboa Drive"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Serveur de développement"
 * )
 *
 * @OA\Server(
 *     url="https://api.mboadrive.com",
 *     description="Serveur de production"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Utiliser le token Bearer obtenu lors de la connexion"
 * )
 *
 * @OA\Schema(
 *     schema="UserResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Jean Mballa"),
 *     @OA\Property(property="email", type="string", format="email", example="jean@example.com"),
 *     @OA\Property(property="phone", type="string", example="651234567"),
 *     @OA\Property(property="profile_photo_url", type="string", nullable=true, example="https://example.com/photo.jpg"),
 *     @OA\Property(property="user_type", type="string", enum={"passenger","driver","both"}, example="passenger"),
 *     @OA\Property(property="date_of_birth", type="string", format="date", nullable=true, example="1990-01-15"),
 *     @OA\Property(property="gender", type="string", enum={"male","female","other"}, nullable=true, example="male"),
 *     @OA\Property(property="bio", type="string", nullable=true, example="Passager régulier"),
 *     @OA\Property(property="city", type="string", nullable=true, example="Douala"),
 *     @OA\Property(property="is_verified", type="boolean", example=false),
 *     @OA\Property(property="phone_verified", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="total_ratings", type="integer", example=25),
 *     @OA\Property(property="emergency_contacts", type="array", @OA\Items(
 *         @OA\Property(property="name", type="string", example="Marie Mballa"),
 *         @OA\Property(property="phone", type="string", example="677123456")
 *     )),
 *     @OA\Property(property="preferences", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
 *     @OA\Property(property="badges", type="array", @OA\Items(
 *         @OA\Property(property="type", type="string", example="verified"),
 *         @OA\Property(property="label", type="string", example="Vérifié"),
 *         @OA\Property(property="icon", type="string", example="verified"),
 *         @OA\Property(property="color", type="string", example="blue")
 *     ))
 * )
 *
 * @OA\Schema(
 *     schema="TripResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="driver", ref="#/components/schemas/UserResource"),
 *     @OA\Property(property="vehicle", ref="#/components/schemas/VehicleResource"),
 *     @OA\Property(property="departure", type="object",
 *         @OA\Property(property="city", type="string", example="Douala"),
 *         @OA\Property(property="address", type="string", example="Rond-point Deido"),
 *         @OA\Property(property="latitude", type="number", format="float", nullable=true, example=4.0614),
 *         @OA\Property(property="longitude", type="number", format="float", nullable=true, example=9.7847),
 *         @OA\Property(property="date", type="string", format="date-time", example="2024-01-20T08:00:00Z"),
 *         @OA\Property(property="formatted_date", type="string", example="20/01/2024 08:00"),
 *         @OA\Property(property="formatted_time", type="string", example="08:00")
 *     ),
 *     @OA\Property(property="arrival", type="object",
 *         @OA\Property(property="city", type="string", example="Yaoundé"),
 *         @OA\Property(property="address", type="string", example="Gare routière Mvan"),
 *         @OA\Property(property="latitude", type="number", format="float", nullable=true, example=3.8667),
 *         @OA\Property(property="longitude", type="number", format="float", nullable=true, example=11.5167),
 *         @OA\Property(property="date", type="string", format="date-time", example="2024-01-20T11:30:00Z"),
 *         @OA\Property(property="formatted_date", type="string", example="20/01/2024 11:30"),
 *         @OA\Property(property="formatted_time", type="string", example="11:30")
 *     ),
 *     @OA\Property(property="seats", type="object",
 *         @OA\Property(property="total", type="integer", example=4),
 *         @OA\Property(property="available", type="integer", example=3),
 *         @OA\Property(property="booked", type="integer", example=1),
 *         @OA\Property(property="remaining", type="integer", example=2)
 *     ),
 *     @OA\Property(property="price_per_seat", type="number", format="float", example=5000),
 *     @OA\Property(property="formatted_price", type="string", example="5 000 FCFA"),
 *     @OA\Property(property="total_distance", type="integer", nullable=true, example=245),
 *     @OA\Property(property="estimated_duration", type="integer", nullable=true, example=210),
 *     @OA\Property(property="preferences", type="object",
 *         @OA\Property(property="instant_booking", type="boolean", example=true),
 *         @OA\Property(property="smoking_allowed", type="boolean", example=false),
 *         @OA\Property(property="pets_allowed", type="boolean", example=false),
 *         @OA\Property(property="max_2_in_back", type="integer", example=2)
 *     ),
 *     @OA\Property(property="status", type="string", enum={"scheduled","in_progress","completed","cancelled"}, example="scheduled"),
 *     @OA\Property(property="is_full", type="boolean", example=false),
 *     @OA\Property(property="can_be_booked", type="boolean", example=true),
 *     @OA\Property(property="is_upcoming", type="boolean", example=true),
 *     @OA\Property(property="additional_info", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="VehicleResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="brand", type="string", example="Toyota"),
 *     @OA\Property(property="model", type="string", example="Corolla"),
 *     @OA\Property(property="color", type="string", example="Blanc"),
 *     @OA\Property(property="year", type="integer", example=2018),
 *     @OA\Property(property="license_plate", type="string", example="LT 123 AA"),
 *     @OA\Property(property="fuel_type", type="string", enum={"gasoline","diesel","hybrid","electric"}, example="gasoline"),
 *     @OA\Property(property="seats", type="integer", example=5),
 *     @OA\Property(property="available_seats", type="integer", example=4),
 *     @OA\Property(property="air_conditioning", type="boolean", example=true),
 *     @OA\Property(property="is_verified", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="full_name", type="string", example="Toyota Corolla 2018"),
 *     @OA\Property(property="main_photo", type="string", nullable=true, example="https://example.com/vehicle.jpg"),
 *     @OA\Property(property="photos", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="badges", type="array", @OA\Items(
 *         @OA\Property(property="type", type="string"),
 *         @OA\Property(property="label", type="string"),
 *         @OA\Property(property="icon", type="string"),
 *         @OA\Property(property="color", type="string")
 *     ))
 * )
 *
 * @OA\Schema(
 *     schema="BookingResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="trip", ref="#/components/schemas/TripResource"),
 *     @OA\Property(property="passenger", ref="#/components/schemas/UserResource"),
 *     @OA\Property(property="seats_booked", type="integer", example=2),
 *     @OA\Property(property="total_amount", type="number", format="float", example=10000),
 *     @OA\Property(property="formatted_amount", type="string", example="10 000 FCFA"),
 *     @OA\Property(property="passenger_message", type="string", nullable=true),
 *     @OA\Property(property="pickup", type="object", nullable=true,
 *         @OA\Property(property="address", type="string"),
 *         @OA\Property(property="latitude", type="number", format="float"),
 *         @OA\Property(property="longitude", type="number", format="float")
 *     ),
 *     @OA\Property(property="dropoff", type="object", nullable=true,
 *         @OA\Property(property="address", type="string"),
 *         @OA\Property(property="latitude", type="number", format="float"),
 *         @OA\Property(property="longitude", type="number", format="float")
 *     ),
 *     @OA\Property(property="status", type="string", enum={"pending","confirmed","cancelled","completed","no_show"}, example="pending"),
 *     @OA\Property(property="status_label", type="string", example="En attente de confirmation"),
 *     @OA\Property(property="can_be_cancelled", type="boolean", example=true),
 *     @OA\Property(property="is_picked_up", type="boolean", example=false),
 *     @OA\Property(property="is_dropped_off", type="boolean", example=false),
 *     @OA\Property(property="payment_status", type="string", enum={"unpaid","pending","paid"}, example="unpaid"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Une erreur s'est produite"),
 *     @OA\Property(property="error", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Opération réussie"),
 *     @OA\Property(property="data", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="pagination", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="last_page", type="integer", example=5),
 *         @OA\Property(property="per_page", type="integer", example=10),
 *         @OA\Property(property="total", type="integer", example=48)
 *     )
 * )
 */
class SwaggerController extends Controller
{
    // Ce controller sert uniquement à définir les annotations de base
}
