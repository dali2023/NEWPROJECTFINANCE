<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'un prospect commercial.
 *
 * Le prospect est volontairement distinct du client : il peut être importé,
 * qualifié et suivi sans créer de fiche client. La conversion crée ensuite un
 * client, un projet et un contrat brouillon dans une seule transaction.
 */
enum ProspectStatus: string implements LabeledEnum
{
    case New = 'new';
    case Qualified = 'qualified';
    case InDiscussion = 'in_discussion';
    case Won = 'won';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::Qualified => 'Qualifié',
            self::InDiscussion => 'En discussion',
            self::Won => 'Gagné — à convertir',
            self::Converted => 'Converti',
            self::Lost => 'Perdu',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'badge--info',
            self::Qualified => 'badge--neutral',
            self::InDiscussion => 'badge--warning',
            self::Won => 'badge--success',
            self::Converted => 'badge--primary',
            self::Lost => 'badge--danger',
        };
    }

    public function isConverted(): bool
    {
        return self::Converted === $this;
    }

    public function isLost(): bool
    {
        return self::Lost === $this;
    }

    /** Un prospect encore ouvert peut être qualifié, modifié ou converti. */
    public function isOpen(): bool
    {
        return !$this->isConverted() && !$this->isLost();
    }

    /**
     * Convertit une valeur importée (libellé français, code technique ou
     * variante courante) en statut. Retourne null si la valeur est inconnue.
     */
    public static function tryFromImport(?string $value): ?self
    {
        $normalized = self::normalizeImportValue((string) $value);
        if ('' === $normalized) {
            return self::New;
        }

        return match ($normalized) {
            'new', 'nouveau', 'nouvelle', 'prospect' => self::New,
            'qualified', 'qualifie', 'qualifiee', 'qualifies', 'qualifiees' => self::Qualified,
            'in discussion', 'in_discussion', 'discussion', 'en discussion', 'negociation', 'en negociation' => self::InDiscussion,
            'won', 'gagne', 'gagnee', 'signe', 'signee' => self::Won,
            'converted', 'converti', 'convertie', 'converties' => self::Converted,
            'lost', 'perdu', 'perdue', 'abandonne', 'abandonnee' => self::Lost,
            default => null,
        };
    }

    private static function normalizeImportValue(string $value): string
    {
        $value = trim($value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (false !== $transliterated) {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim($value);
    }

    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
