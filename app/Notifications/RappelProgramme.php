<?php

namespace App\Notifications;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;

/** Rappel d'un programme : dans le tiroir des notifications et sur le téléphone, jamais par email. */
class RappelProgramme extends NotificationMembre
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        if ($notifiable instanceof User && $notifiable->pushSubscriptions()->exists()) {
            $canaux[] = WebPushChannel::class;
        }

        return $canaux;
    }
}
