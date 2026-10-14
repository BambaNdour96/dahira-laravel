<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property Carbon|null $charte_acceptee_le
 * @property string|null $app_authentication_secret
 * @property list<string>|null $app_authentication_recovery_codes
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasPasskeys
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    use HasPushSubscriptions;
    use Indelebile;
    use InteractsWithPasskeys;

    protected $fillable = [
        'name', 'telephone', 'email', 'password', 'actif', 'derniere_connexion_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'charte_acceptee_le' => 'datetime',
            'doit_changer_mot_de_passe' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->actif && $this->can('panneau.acceder');
    }

    /** @return HasOne<Membre, $this> */
    public function membre(): HasOne
    {
        return $this->hasOne(Membre::class);
    }

    /**
     * Ses nominations en cours (l'historique : withoutGlobalScope(Nomination::EN_COURS)).
     *
     * @return HasMany<Nomination, $this>
     */
    public function nominations(): HasMany
    {
        return $this->hasMany(Nomination::class);
    }

    // Double authentification (application Google Authenticator, Microsoft Authenticator…)

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /** Nom affiché dans l'application d'authentification. */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->name.' ('.$this->telephone.')';
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  array<string>|null  $codes */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes === null ? null : array_values($codes);
        $this->save();
    }

    /** Nom proposé par le téléphone au moment de choisir la clé : le numéro, toujours présent. */
    public function getPassKeyName(): string
    {
        return (string) $this->telephone;
    }

    public function getPassKeyId(): string
    {
        return (string) $this->getKey();
    }

    public function getPassKeyDisplayName(): string
    {
        return (string) $this->name;
    }
}
