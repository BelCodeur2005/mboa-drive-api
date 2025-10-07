<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('payer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            
            // Détails du paiement
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'mobile_money', 'bank_card', 'bank_transfer']);
            $table->enum('provider', ['mtn', 'orange', 'express_union', 'visa', 'mastercard'])->nullable();
            
            // Informations de transaction
            $table->string('transaction_id')->unique()->nullable();
            $table->string('external_reference')->nullable(); // Référence du prestataire externe
            $table->string('phone_number')->nullable(); // Pour Mobile Money
            
            // États
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded']);
            $table->text('failure_reason')->nullable();
            $table->json('provider_response')->nullable(); // Réponse du prestataire de paiement
            
            // Commission
            $table->decimal('commission_rate', 5, 2)->default(10.00); // Pourcentage
            $table->decimal('commission_amount', 8, 2)->default(0);
            $table->decimal('net_amount', 10, 2); // Montant après commission
            
            // Timestamps
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            
            // Index
            $table->index(['booking_id', 'status']);
            $table->index(['payer_id', 'status']);
            $table->index(['transaction_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};