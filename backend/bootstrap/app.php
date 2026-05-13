<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias des middlewares custom utilises dans routes/api.php.
        $middleware->alias([
            'auth.token' => App\Http\Middleware\AuthenticateApiToken::class,
            'auth.client.session' => App\Http\Middleware\AuthenticateClientSession::class,
            'log.admin' => App\Http\Middleware\LogAdminAction::class,
            'role' => App\Http\Middleware\EnsureUserHasRole::class,
        ]);


        // TrustProxies (B6) : le nginx interne tourne en HTTP derriere un
        // reverse-proxy TLS (Cloudflare, Caddy, AWS ELB...). Sans cette
        // configuration, Laravel ignore X-Forwarded-Proto et croit que la
        // requete est en HTTP, ce qui casse :
        //   - les cookies "Secure" (jamais envoyes)
        //   - les URLs absolues generees (en http:// au lieu de https://)
        //   - les checks request->isSecure() / secure_url()
        // '*' fait confiance a tous les proxies amont — acceptable pour ce
        // setup. A restreindre a une IP/range si l infra est connue.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
