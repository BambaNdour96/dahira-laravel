<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutMembre: string implements HasColor, HasLabel
{
    case EnAttente = 'en_attente';
    case ACorriger = 'a_corriger';
    case Rejete = 'rejete';
    case Accepte = 'accepte';
    case Expire = 'expire';
    case Valide = 'valide';
    case Suspendu = 'suspendu';
    case Radie = 'radie';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::ACorriger => 'À corriger',
            self::Rejete => 'Rejeté',
            self::Accepte => 'Accepté, paiement attendu',
            self::Expire => 'Délai de paiement dépassé',
            self::Valide => 'Validé',
            self::Suspendu => 'Suspendu',
            self::Radie => 'Radié',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'warning',
            self::ACorriger => 'info',
            self::Rejete, self::Radie => 'danger',
            self::Accepte => 'primary',
            self::Valide => 'success',
            self::Expire, self::Suspendu => 'gray',
        };
    }

    /**
     * Règle 4.1 : accepter, puis payer les frais de carte pour être validé.
     *
     * @return list<self>
     */
    public function transitionsPossibles(): array
    {
        return match ($this) {
            self::EnAttente => [self::Accepte, self::Valide, self::ACorriger, self::Rejete],
            self::ACorriger => [self::EnAttente],
            self::Accepte => [self::Valide, self::Expire, self::Rejete],
            self::Expire => [self::EnAttente, self::Valide],
            self::Valide => [self::Suspendu, self::Radie],
            self::Suspendu => [self::Valide, self::Radie],
            self::Rejete, self::Radie => [],
        };
    }

    public function peutPasserA(self $cible): bool
    {
        return in_array($cible, $this->transitionsPossibles(), true);
    }

    public function exigeMotif(): bool
    {
        return in_array($this, [self::ACorriger, self::Rejete, self::Suspendu, self::Radie], true);
    }
}
