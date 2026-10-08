<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\NotificationMembre;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MesNotificationsController extends Controller
{
    /** Les notifications du membre ; les nouvelles sont mises en avant, puis marquées comme lues. */
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $notifications = $utilisateur->notifications()
            ->where('type', NotificationMembre::class)
            ->latest()
            ->paginate(15);

        $nouvelles = $utilisateur->unreadNotifications()
            ->where('type', NotificationMembre::class)
            ->pluck('id')
            ->all();

        $utilisateur->unreadNotifications()
            ->where('type', NotificationMembre::class)
            ->update(['read_at' => now()]);

        return view('espace.notifications', [
            'notifications' => $notifications,
            'nouvelles' => $nouvelles,
        ]);
    }
}
