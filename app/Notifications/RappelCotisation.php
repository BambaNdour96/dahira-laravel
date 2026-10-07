<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RappelCotisation extends Notification
{
    use Queueable;

    /** @param  list<string>  $mois */
    public function __construct(
        public string $prenom,
        public array $mois,
        public int $montant,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rappel de cotisation – Daara Nouroud Daareyni Kaolack')
            ->greeting('Assalamou aleykoum '.$this->prenom.',')
            ->line('Vos cotisations de '.implode(', ', $this->mois).' restent à régler, soit '
                .number_format($this->montant, 0, ',', ' ').' FCFA.')
            ->action('Payer mes cotisations', route('cotisations'))
            ->line('Si vous avez déjà payé, merci de ne pas tenir compte de ce message.')
            ->salutation('Jërëjëf, le bureau de la Daara');
    }
}
