<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification adressée à un membre : dans son compte, et par email s'il en a un.
 */
class NotificationMembre extends Notification
{
    use Queueable;

    public function __construct(
        public string $titre,
        public string $message,
        public ?string $lien = null,
        public ?string $libelleLien = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => $this->titre,
            'message' => $this->message,
            'lien' => $this->lien,
            'libelle_lien' => $this->libelleLien,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = (new MailMessage)
            ->subject($this->titre.' – Daara Nouroud Daareyni Kaolack')
            ->greeting('Assalamou aleykoum,')
            ->line($this->message);

        if ($this->lien !== null) {
            $email->action($this->libelleLien ?? 'Ouvrir mon compte', $this->lien);
        }

        return $email->salutation('Jërëjëf, le bureau de la Daara');
    }
}
