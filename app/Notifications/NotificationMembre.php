<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification adressée à un membre : dans son compte, et par email s'il en a un.
 */
class NotificationMembre extends Notification implements ShouldQueue
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
        return $notifiable instanceof User && $notifiable->email_verified_at !== null && filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return [
            'titre' => $this->titre,
            'message' => $this->message,
            'lien' => self::chemin($this->lien),
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
            $email->action($this->libelleLien ?? 'Ouvrir mon compte', url((string) self::chemin($this->lien)));
        }

        return $email->salutation('Jërëjëf, le bureau de la Daara');
    }

    /** Chemin relatif d'un lien (par exemple /ma-carte), pour fonctionner quelle que soit l'adresse du site. */
    public static function chemin(?string $lien): ?string
    {
        if ($lien === null || $lien === '') {
            return null;
        }

        $chemin = parse_url($lien, PHP_URL_PATH);
        $requete = parse_url($lien, PHP_URL_QUERY);

        return (is_string($chemin) && $chemin !== '' ? $chemin : '/')
            .(is_string($requete) && $requete !== '' ? '?'.$requete : '');
    }
}
