<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
