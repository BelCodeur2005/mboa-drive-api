<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // booking_confirmed, trip_started, payment_received, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Données contextuelles (IDs, URLs, etc.)

            // Relations optionnelles
            $table->foreignId('related_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained()->onDelete('cascade');

            // États
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');

            // Notifications push
            $table->boolean('sent_push')->default(false);
            $table->timestamp('sent_push_at')->nullable();
            $table->json('push_response')->nullable();

            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
