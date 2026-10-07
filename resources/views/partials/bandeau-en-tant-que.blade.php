@php($impersonateur = \App\Services\ConnexionEnTantQue::original())
@if ($impersonateur && auth()->check())
    <div style="position:relative;z-index:50;background:#b3850f;color:#fff;padding:8px 16px;text-align:center;font-size:14px;font-family:system-ui,sans-serif">
        Vous consultez la plateforme en tant que <strong>{{ auth()->user()->name }}</strong>.
        <form method="POST" action="{{ route('en-tant-que.retour') }}" style="display:inline;margin-left:8px">
            @csrf
            <button type="submit" style="background:#fff;color:#1e5223;border:0;border-radius:6px;padding:4px 10px;font-weight:600;cursor:pointer">
                Revenir à mon compte
            </button>
        </form>
    </div>
@endif
