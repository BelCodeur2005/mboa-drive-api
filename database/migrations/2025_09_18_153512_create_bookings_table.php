<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->foreignId('passenger_id')->constrained('users')->onDelete('cascade');

            // Détails de la réservation
            $table->integer('seats_booked');
            $table->decimal('total_amount', 10, 2);
            $table->text('passenger_message')->nullable();

            // Points de prise en charge spécifiques (optionnel)
            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_latitude', 10, 8)->nullable();
            $table->decimal('pickup_longitude', 11, 8)->nullable();

            $table->string('dropoff_address')->nullable();
            $table->decimal('dropoff_latitude', 10, 8)->nullable();
            $table->decimal('dropoff_longitude', 11, 8)->nullable();

            // Statuts et timing
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Suivi du trajet
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('dropped_off_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(['passenger_id', 'status']);
            $table->index(['trip_id', 'status']);

            // Contrainte unique pour éviter les doublons
            $table->unique(['trip_id', 'passenger_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};
