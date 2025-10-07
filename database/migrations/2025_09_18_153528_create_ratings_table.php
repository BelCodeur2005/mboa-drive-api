<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('rater_id')->constrained('users')->onDelete('cascade'); // Qui donne la note
            $table->foreignId('rated_id')->constrained('users')->onDelete('cascade'); // Qui reçoit la note
            $table->integer('rating')->comment('Note de 1 à 5');
            $table->text('comment')->nullable();
            $table->enum('type', ['driver_to_passenger', 'passenger_to_driver']);
            $table->timestamps();

            // Contraintes
            $table->unique(['booking_id', 'rater_id', 'rated_id']);
            $table->index(['rated_id', 'rating']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ratings');
    }
};
