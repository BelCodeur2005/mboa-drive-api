<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('brand'); // Toyota, Honda, etc.
            $table->string('model'); // Camry, Civic, etc.
            $table->string('color');
            $table->string('license_plate')->unique();
            $table->integer('year');
            $table->integer('seats'); // nombre total de places
            $table->string('fuel_type')->default('petrol'); // petrol, diesel, hybrid
            $table->boolean('air_conditioning')->default(false);
            $table->json('photos')->nullable(); // URLs des photos du véhicule
            $table->text('description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicles');
    }
};
