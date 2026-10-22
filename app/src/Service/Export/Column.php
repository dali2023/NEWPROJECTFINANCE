<?php

declare(strict_types=1);

namespace App\Service\Export;

/**
 * Description d'une colonne d'export Excel.
 *
 * Les montants et taux sont écrits comme valeurs numériques avec un format
 * d'affichage explicite : le classeur reste exploitable dans Excel (sommes,
 * tris) tout en conservant la précision métier (3 décimales pour les montants,
 * 4 pour les taux).
 */
final class Column
{
    private function __construct(
        public readonly string $label,
        public readonly int $width,
        public readonly ?string $format,
        public readonly bool $total,
        public readonly string $type,
    ) {
    }

    public static function text(string $label, int $width = 20): self
    {
        return new self($label, $width, null, false, 'text');
    }

    public static function money(string $label, int $width = 15, bool $total = true): self
    {
        return new self($label, $width, '#,##0.000', $total, 'money');
    }

    public static function rate(string $label, int $width = 10): self
    {
        return new self($label, $width, '0.0000" %"', false, 'rate');
    }

    public static function decimal(string $label, int $width = 12, bool $total = false): self
    {
        return new self($label, $width, '#,##0.0000', $total, 'decimal');
    }

    public static function integer(string $label, int $width = 8, bool $total = false): self
    {
        return new self($label, $width, '0', $total, 'integer');
    }

    public static function date(string $label, int $width = 13): self
    {
        return new self($label, $width, 'dd/mm/yyyy', false, 'date');
    }

    public static function dateTime(string $label, int $width = 17): self
    {
        return new self($label, $width, 'dd/mm/yyyy hh:mm', false, 'date');
    }

    public static function boolean(string $label, int $width = 10): self
    {
        return new self($label, $width, null, false, 'boolean');
    }
}
