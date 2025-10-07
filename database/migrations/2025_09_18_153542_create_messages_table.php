<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade'); // Message lié à une réservation
            
            // Contenu du message
            $table->text('message');
            $table->enum('type', ['text', 'location', 'image', 'system'])->default('text');
            $table->json('metadata')->nullable(); // Données additionnelles (coordonnées GPS, etc.)
            
            // État du message
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted_by_sender')->default(false);
            $table->boolean('is_deleted_by_recipient')->default(false);
            
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index(['sender_id', 'recipient_id', 'created_at']);
            $table->index(['booking_id', 'created_at']);
            $table->index(['is_read', 'recipient_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};