<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Demande au membre de confirmer son adresse email, avec un lien signé valable 7 jours. */
class ConfirmerEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $lien = URL::temporarySignedRoute('email.confirmer', now()->addDays(7), [
            'user' => $notifiable->getKey(),
            'hash' => sha1((string) $notifiable->email),
        ]);

        return (new MailMessage)
            ->subject('Confirmez votre adresse email – Daara Nouroud Daareyni')
            ->greeting('Assalamou aleykoum '.$notifiable->name.',')
            ->line('Pour recevoir vos reçus et pouvoir retrouver votre mot de passe, confirmez votre adresse email.')
            ->action('Confirmer mon adresse', $lien)
            ->line('Ce lien est valable 7 jours. Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.');
    }
}
