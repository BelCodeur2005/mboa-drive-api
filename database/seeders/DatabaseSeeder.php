<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Trip;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer des utilisateurs de test
        $this->createTestUsers();

        // Créer des véhicules de test
        $this->createTestVehicles();

        // Créer des trajets de test
        $this->createTestTrips();
    }

    /**
     * Créer des utilisateurs de test
     */
    private function createTestUsers(): void
    {
        // Administrateur
        User::create([
            'name' => 'Admin Mboa Drive',
            'email' => 'admin@mboadrive.cm',
            'phone' => '+237690000001',
            'password' => Hash::make('password123'),
            'user_type' => 'both',
            'city' => 'Douala',
            'is_verified' => true,
            'phone_verified' => true,
            'average_rating' => 5.0,
            'total_ratings' => 0,
        ]);

        // Conducteur test 1
        User::create([
            'name' => 'Jean Mballa',
            'email' => 'jean.mballa@example.com',
            'phone' => '+237690000002',
            'password' => Hash::make('password123'),
            'user_type' => 'driver',
            'city' => 'Douala',
            'date_of_birth' => '1985-03-15',
            'gender' => 'male',
            'bio' => 'Conducteur expérimenté, 10 ans d\'expérience. Véhicule propre et climatisé.',
            'is_verified' => true,
            'phone_verified' => true,
            'average_rating' => 4.8,
            'total_ratings' => 45,
            'preferences' => [
                'music_style' => 'makossa',
                'conversation' => 'moderate',
                'temperature' => 22
            ]
        ]);

        // Conducteur test 2
        User::create([
            'name' => 'Marie Ngo',
            'email' => 'marie.ngo@example.com',
            'phone' => '+237690000003',
            'password' => Hash::make('password123'),
            'user_type' => 'driver',
            'city' => 'Yaoundé',
            'date_of_birth' => '1990-07-22',
            'gender' => 'female',
            'bio' => 'Conductrice prudente et ponctuelle. Trajet confortable garanti.',
            'is_verified' => true,
            'phone_verified' => true,
            'average_rating' => 4.9,
            'total_ratings' => 32,
            'preferences' => [
                'music_style' => 'gospel',
                'conversation' => 'friendly',
                'temperature' => 20
            ]
        ]);

        // Passager test
        User::create([
            'name' => 'Paul Talla',
            'email' => 'paul.talla@example.com',
            'phone' => '+237690000004',
            'password' => Hash::make('password123'),
            'user_type' => 'passenger',
            'city' => 'Douala',
            'date_of_birth' => '1995-12-08',
            'gender' => 'male',
            'is_verified' => false,
            'phone_verified' => true,
            'average_rating' => 4.7,
            'total_ratings' => 12,
        ]);

        // Utilisateur mixte (conducteur et passager)
        User::create([
            'name' => 'Grace Fouda',
            'email' => 'grace.fouda@example.com',
            'phone' => '+237690000005',
            'password' => Hash::make('password123'),
            'user_type' => 'both',
            'city' => 'Bafoussam',
            'date_of_birth' => '1988-09-14',
            'gender' => 'female',
            'bio' => 'Étudiante en master, voyage régulièrement entre Bafoussam et Douala.',
            'is_verified' => true,
            'phone_verified' => true,
            'average_rating' => 4.6,
            'total_ratings' => 18,
        ]);

        $this->command->info('✅ Utilisateurs de test créés avec succès');
    }

    /**
     * Créer des véhicules de test
     */
    private function createTestVehicles(): void
    {
        $vehicles = [
            [
                'user_id' => 2, // Jean Mballa
                'brand' => 'Toyota',
                'model' => 'Camry',
                'color' => 'Blanc',
                'license_plate' => 'LT-123-CM',
                'year' => 2019,
                'seats' => 5,
                'fuel_type' => 'petrol',
                'air_conditioning' => true,
                'description' => 'Véhicule bien entretenu, climatisation efficace, sièges confortables.',
                'is_verified' => true,
                'is_active' => true,
            ],
            [
                'user_id' => 3, // Marie Ngo
                'brand' => 'Honda',
                'model' => 'Civic',
                'color' => 'Gris',
                'license_plate' => 'CE-456-CM',
                'year' => 2020,
                'seats' => 5,
                'fuel_type' => 'petrol',
                'air_conditioning' => true,
                'description' => 'Voiture récente, très économique, parfaite pour longs trajets.',
                'is_verified' => true,
                'is_active' => true,
            ],
            [
                'user_id' => 5, // Grace Fouda
                'brand' => 'Hyundai',
                'model' => 'Elantra',
                'color' => 'Rouge',
                'license_plate' => 'OU-789-CM',
                'year' => 2018,
                'seats' => 5,
                'fuel_type' => 'petrol',
                'air_conditioning' => false,
                'description' => 'Véhicule fiable pour trajets interurbains.',
                'is_verified' => true,
                'is_active' => true,
            ],
        ];

        foreach ($vehicles as $vehicleData) {
            Vehicle::create($vehicleData);
        }

        $this->command->info('✅ Véhicules de test créés avec succès');
    }

    /**
     * Créer des trajets de test
     */
    private function createTestTrips(): void
    {
        $trips = [
            [
                'driver_id' => 2,
                'vehicle_id' => 1,
                'departure_city' => 'Douala',
                'departure_address' => 'Rond-point Deido, Douala',
                'departure_latitude' => 4.0511,
                'departure_longitude' => 9.7679,
                'arrival_city' => 'Yaoundé',
                'arrival_address' => 'Gare routière Mvog-Mbi, Yaoundé',
                'arrival_latitude' => 3.8480,
                'arrival_longitude' => 11.5021,
                'departure_date' => now()->addDays(1)->setHour(8)->setMinute(0),
                'available_seats' => 3,
                'total_seats' => 3,
                'price_per_seat' => 3500,
                'total_distance' => 250,
                'estimated_duration' => 180,
                'instant_booking' => true,
                'smoking_allowed' => false,
                'pets_allowed' => false,
                'additional_info' => 'Départ ponctuel à 8h. Arrêt possible à Edéa.',
                'status' => 'scheduled',
            ],
            [
                'driver_id' => 3,
                'vehicle_id' => 2,
                'departure_city' => 'Yaoundé',
                'departure_address' => 'Carrefour Warda, Yaoundé',
                'departure_latitude' => 3.8480,
                'departure_longitude' => 11.5021,
                'arrival_city' => 'Bafoussam',
                'arrival_address' => 'Gare routière, Bafoussam',
                'arrival_latitude' => 5.4781,
                'arrival_longitude' => 10.4172,
                'departure_date' => now()->addDays(2)->setHour(14)->setMinute(30),
                'available_seats' => 4,
                'total_seats' => 4,
                'price_per_seat' => 2500,
                'total_distance' => 180,
                'estimated_duration' => 150,
                'instant_booking' => true,
                'smoking_allowed' => false,
                'pets_allowed' => true,
                'additional_info' => 'Trajet direct, musique d\'ambiance.',
                'status' => 'scheduled',
            ],
            [
                'driver_id' => 5,
                'vehicle_id' => 3,
                'departure_city' => 'Bafoussam',
                'departure_address' => 'Marché A, Bafoussam',
                'departure_latitude' => 5.4781,
                'departure_longitude' => 10.4172,
                'arrival_city' => 'Douala',
                'arrival_address' => 'Carrefour Elf Akwa, Douala',
                'arrival_latitude' => 4.0511,
                'departure_longitude' => 9.7679,
                'departure_date' => now()->addDays(3)->setHour(6)->setMinute(0),
                'available_seats' => 2,
                'total_seats' => 2,
                'price_per_seat' => 4000,
                'total_distance' => 280,
                'estimated_duration' => 200,
                'instant_booking' => false,
                'smoking_allowed' => false,
                'pets_allowed' => false,
                'additional_info' => 'Départ très tôt le matin. Confirmation requise.',
                'status' => 'scheduled',
            ],
        ];

        foreach ($trips as $tripData) {
            Trip::create($tripData);
        }

        $this->command->info('✅ Trajets de test créés avec succès');
    }
}
