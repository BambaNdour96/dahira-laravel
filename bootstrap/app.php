<?php

use App\Http\Middleware\AppliquerIndicatif;
use App\Http\Middleware\ChangementMotDePasseObligatoire;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ExpirationInactivite;
use App\Http\Middleware\ModeMaintenance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [ExpirationInactivite::class]);
        $middleware->web(append: [AppliquerIndicatif::class]);
        $middleware->web(append: [EnTetesSecurite::class]);
        $middleware->web(append: [ChangementMotDePasseObligatoire::class]);
        $middleware->web(append: [ModeMaintenance::class]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
