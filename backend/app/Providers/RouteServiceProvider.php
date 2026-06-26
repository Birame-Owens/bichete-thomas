<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/dashboard';

    public function boot(): void
    {
        $this->routes(function () {
            // Routes Web
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Routes API
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // ✅ Routes Client
            Route::middleware('web') // ou "api" si c'est une API
                ->prefix('client')
                ->group(base_path('routes/client.php'));
        });
    }
}
