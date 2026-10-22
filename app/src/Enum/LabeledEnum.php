<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enumération affichable : chaque cas porte un libellé français utilisé par
 * l'interface, le formulaire et les exports.
 */
interface LabeledEnum extends \BackedEnum
{
    /** Libellé français destiné à l'interface. */
    public function label(): string;

    /** Classe CSS du badge associé (styles « badge-* »). */
    public function badgeClass(): string;

    /**
     * @return array<string, string> libellé => valeur, pour les listes déroulantes
     */
    public static function choices(): array;
}
