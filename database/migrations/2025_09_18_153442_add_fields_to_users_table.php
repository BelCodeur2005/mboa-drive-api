<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->after('email');
            $table->string('profile_photo')->nullable()->after('phone');
            $table->enum('user_type', ['passenger', 'driver', 'both'])->default('passenger');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('bio')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('average_rating', 3, 2)->default(5.00);
            $table->integer('total_ratings')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->json('emergency_contacts')->nullable();
            $table->json('preferences')->nullable(); // musique, climatisation, etc.
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'profile_photo', 'user_type', 'date_of_birth',
                'gender', 'bio', 'city', 'is_verified', 'phone_verified',
                'is_active', 'average_rating', 'total_ratings',
                'last_active_at', 'emergency_contacts', 'preferences'
            ]);
        });
    }
};
