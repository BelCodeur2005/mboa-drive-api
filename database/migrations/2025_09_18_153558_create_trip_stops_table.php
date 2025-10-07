<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            
            // Informations du point d'arrêt
            $table->string('city');
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Ordre du point d'arrêt dans le trajet
            $table->integer('order');
            
            // Horaires estimés
            $table->time('estimated_arrival_time')->nullable();
            $table->time('estimated_departure_time')->nullable();
            
            // Coût additionnel pour ce point d'arrêt
            $table->decimal('additional_cost', 8, 2)->default(0);
            
            $table->timestamps();
            
            // Index
            $table->index(['trip_id', 'order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('trip_stops');
    }
};