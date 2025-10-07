<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('firebase.messaging', function ($app) {
            $credentialsPath = config('firebase.credentials');

            if (!file_exists($credentialsPath)) {
                throw new \Exception("Fichier credentials Firebase introuvable : {$credentialsPath}");
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);

            return $factory->createMessaging();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
