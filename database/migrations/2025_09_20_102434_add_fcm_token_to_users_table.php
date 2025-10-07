<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('remember_token');
            $table->json('device_info')->nullable()->after('fcm_token');
            $table->boolean('push_notifications_enabled')->default(true)->after('device_info');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('push_notifications_enabled');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'fcm_token',
                'device_info',
                'push_notifications_enabled',
                'fcm_token_updated_at'
            ]);
        });
    }
};
