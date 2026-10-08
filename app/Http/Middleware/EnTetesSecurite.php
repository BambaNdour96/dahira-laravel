<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité envoyés au navigateur sur toutes les pages.
 * Le site des membres reçoit en plus une politique de sécurité du contenu (CSP)
 * qui n'autorise que les scripts de la plateforme, signés par un nonce unique.
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $panneau = $request->is('admin', 'admin/*', 'livewire*', 'filament/*');

        if (! $panneau) {
            Vite::useCspNonce();
        }

        $response = $next($request);
        $entetes = $response->headers;

        $entetes->set('X-Content-Type-Options', 'nosniff');
        $entetes->set('X-Frame-Options', 'DENY');
        $entetes->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $entetes->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $entetes->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $entetes->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $panneau && ! $entetes->has('Content-Security-Policy')) {
            $entetes->set('Content-Security-Policy', self::politique((string) Vite::cspNonce()));
        }

        return $response;
    }

    private static function politique(string $nonce): string
    {
        // En développement, on autorise le serveur Vite (npm run dev).
        $vite = app()->isLocal() ? ' http://localhost:5173 http://127.0.0.1:5173' : '';
        $viteWs = app()->isLocal() ? ' ws://localhost:5173 ws://127.0.0.1:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-inline' https:{$vite}",
            "style-src 'self' 'unsafe-inline'{$vite}",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'{$viteWs}",
            "object-src 'none'",            "worker-src 'self'",            "manifest-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
