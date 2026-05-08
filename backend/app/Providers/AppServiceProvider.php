<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En production, force toutes les URLs generees par Laravel (route(),
        // url(), redirects, links dans les emails, etc.) a utiliser le schema
        // https. Necessaire car le nginx interne tourne en HTTP derriere un
        // reverse-proxy TLS : sans ca, Laravel genererait des http:// dans les
        // mails de confirmation, callbacks Stripe/PayTech, etc.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
