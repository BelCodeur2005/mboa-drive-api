<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            // Points de départ et d'arrivée
            $table->string('departure_city');
            $table->string('departure_address');
            $table->decimal('departure_latitude', 10, 8)->nullable();
            $table->decimal('departure_longitude', 11, 8)->nullable();

            $table->string('arrival_city');
            $table->string('arrival_address');
            $table->decimal('arrival_latitude', 10, 8)->nullable();
            $table->decimal('arrival_longitude', 11, 8)->nullable();

            // Timing
            $table->dateTime('departure_date');
            $table->dateTime('arrival_date')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_days')->nullable(); // ['monday', 'tuesday', etc.]

            // Capacité et prix
            $table->integer('available_seats');
            $table->integer('total_seats');
            $table->decimal('price_per_seat', 10, 2);
            $table->decimal('total_distance', 8, 2)->nullable(); // en km
            $table->integer('estimated_duration')->nullable(); // en minutes

            // Préférences et règles
            $table->boolean('instant_booking')->default(true);
            $table->boolean('smoking_allowed')->default(false);
            $table->boolean('pets_allowed')->default(false);
            $table->integer('max_2_in_back')->default(2);
            $table->text('additional_info')->nullable();

            // Statuts
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // Index pour les recherches
            $table->index(['departure_city', 'arrival_city', 'departure_date']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('trips');
    }
};
