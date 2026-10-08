<?php

namespace App\Providers;

use App\Models\Carte;
use App\Models\Membre;
use App\Models\Paiement;
use App\Observers\AvisMembreObserver;
use App\Observers\TachesObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Notifications adressées aux membres.
        Membre::observe(AvisMembreObserver::class);
        Paiement::observe(AvisMembreObserver::class);
        Carte::observe(AvisMembreObserver::class);

        // Notifications de tâches pour le bureau.
        Membre::observe(TachesObserver::class);
        Paiement::observe(TachesObserver::class);

        // Mots de passe : au moins 8 caractères, une lettre et un chiffre ; en ligne, refus des mots de passe ayant fuité.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        // En ligne, toutes les adresses générées sont en HTTPS.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('inscription', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('connexion', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));

        // Email « mot de passe oublié », en français.
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $email = method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : '';

            return (new MailMessage)
                ->subject('Nouveau mot de passe – Daara Nouroud Daareyni Kaolack')
                ->greeting('Assalamou aleykoum,')
                ->line('Vous avez demandé à changer le mot de passe de votre compte.')
                ->action('Choisir un nouveau mot de passe', route('password.reset', ['token' => $token, 'email' => $email]))
                ->line('Ce lien est valable 60 minutes.')
                ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement ce message.')
                ->salutation('Jërëjëf, le bureau de la Daara');
        });
    }
}
