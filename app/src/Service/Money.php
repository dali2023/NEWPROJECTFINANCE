<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Arithmétique décimale exacte pour tous les montants du domaine.
 *
 * Le cahier des charges impose des champs décimaux, jamais des flottants
 * binaires (§5.1). Les montants circulent donc sous forme de chaînes et sont
 * calculés avec bcmath : aucune perte de précision, aucun arrondi implicite.
 *
 * Convention : échelle 3 pour les montants (millimes), 4 pour les taux.
 */
final class Money
{
    public const SCALE = 3;
    public const RATE_SCALE = 4;

    public static function zero(int $scale = self::SCALE): string
    {
        return '0.'.str_repeat('0', $scale);
    }

    /** Normalise une saisie utilisateur (« 1 234,5 » → « 1234.500 »). */
    public static function normalize(string|int|float|null $value, int $scale = self::SCALE): string
    {
        if (null === $value) {
            return self::zero($scale);
        }

        if (\is_int($value)) {
            return self::round((string) $value, $scale);
        }

        $string = trim((string) $value);
        if ('' === $string) {
            return self::zero($scale);
        }

        // Espaces insécables de mise en forme et virgule décimale française.
        $string = str_replace(["\u{202F}", "\u{00A0}", ' '], '', $string);
        if (str_contains($string, ',')) {
            $string = str_replace(['.', ','], ['', '.'], $string);
        }

        if (!preg_match('/^-?\d+(\.\d+)?$/', $string)) {
            throw new \InvalidArgumentException(sprintf('Montant invalide : "%s".', $value));
        }

        return self::round($string, $scale);
    }

    /**
     * Arrondi commercial (demi vers le haut) à l'échelle demandée,
     * entièrement déterministe — exigence §6.8 sur les arrondis.
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '+-');
        $parts = explode('.', $absolute, 2);
        $integerPart = $parts[0] === '' ? '0' : $parts[0];
        $fraction = $parts[1] ?? '';

        $fraction = str_pad($fraction, $scale + 1, '0');
        $roundDigit = (int) $fraction[$scale];
        $keptFraction = substr($fraction, 0, $scale);

        $digits = $integerPart.$keptFraction;
        if ($roundDigit >= 5) {
            $digits = bcadd($digits, '1', 0);
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $resultInteger = substr($digits, 0, -$scale) ?: '0';
        $resultFraction = $scale > 0 ? substr($digits, -$scale) : '';

        return ($negative ? '-' : '').$resultInteger.($scale > 0 ? '.'.$resultFraction : '');
    }

    public static function add(string $a, string $b, int $scale = self::SCALE): string
    {
        return self::round(bcadd($a, $b, $scale + 2), $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::SCALE): string
    {
        return self::round(bcsub($a, $b, $scale + 2), $scale);
    }

    public static function mul(string $a, string $b, int $scale = self::SCALE): string
    {
        return self::round(bcmul($a, $b, $scale + 4), $scale);
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        if (0 === self::compare($b, self::zero($scale + 1))) {
            throw new \DivisionByZeroError('Division par zéro.');
        }

        return self::round(bcdiv($a, $b, $scale + 4), $scale);
    }

    public static function compare(string $a, string $b, int $scale = self::SCALE): int
    {
        return bccomp($a, $b, $scale);
    }

    public static function isZero(string $value, int $scale = self::SCALE): bool
    {
        return 0 === self::compare($value, self::zero($scale), $scale);
    }

    public static function isNegative(string $value, int $scale = self::SCALE): bool
    {
        return self::compare($value, self::zero($scale), $scale) < 0;
    }

    public static function isPositive(string $value, int $scale = self::SCALE): bool
    {
        return self::compare($value, self::zero($scale), $scale) > 0;
    }

    public static function abs(string $value): string
    {
        return ltrim($value, '-');
    }

    public static function min(string $a, string $b, int $scale = self::SCALE): string
    {
        return self::compare($a, $b, $scale) <= 0 ? $a : $b;
    }

    public static function max(string $a, string $b, int $scale = self::SCALE): string
    {
        return self::compare($a, $b, $scale) >= 0 ? $a : $b;
    }

    /** Applique un pourcentage (§6.1, §6.17) : montant × taux / 100. */
    public static function percentOf(string $amount, string $rate, int $scale = self::SCALE): string
    {
        return self::round(bcmul($amount, bcdiv($rate, '100', $scale + 4), $scale + 4), $scale);
    }

    /**
     * Ratio en pourcentage : a / b × 100. Retourne null lorsque le
     * dénominateur est nul, afin d'afficher « non applicable » plutôt que de
     * provoquer une erreur (§6.6, §6.11).
     */
    public static function ratioPercent(string $a, string $b, int $scale = 2): ?string
    {
        if (self::isZero($b, 6)) {
            return null;
        }

        return self::round(bcmul(bcdiv($a, $b, $scale + 4), '100', $scale + 4), $scale);
    }

    /**
     * Répartit un montant selon des clés de pondération en garantissant que la
     * somme des parts est exactement égale au montant source : l'écart
     * d'arrondi résiduel est imputé aux postes les plus importants, dans un
     * ordre déterministe (§6.8, §REC-008).
     *
     * @param array<string, string> $weights clé métier => poids
     *
     * @return array<string, string>
     */
    public static function allocate(string $amount, array $weights, int $scale = self::SCALE): array
    {
        $positiveWeights = array_filter($weights, static fn (string $w): bool => self::compare($w, '0', $scale) > 0);
        if ([] === $positiveWeights) {
            return array_map(static fn (): string => '0.'.str_repeat('0', $scale), $weights);
        }

        $totalWeight = '0';
        foreach ($positiveWeights as $weight) {
            $totalWeight = bcadd($totalWeight, $weight, $scale);
        }

        $shares = [];
        $allocated = '0.'.str_repeat('0', $scale);
        foreach ($weights as $key => $weight) {
            if (!isset($positiveWeights[$key])) {
                $shares[$key] = '0.'.str_repeat('0', $scale);
                continue;
            }
            $share = self::round(bcmul(bcdiv($amount, $totalWeight, 10), $weight, 12), $scale);
            $shares[$key] = $share;
            $allocated = bcadd($allocated, $share, $scale);
        }

        $remainder = self::round(bcsub($amount, $allocated, $scale + 2), $scale);
        if (0 !== self::compare($remainder, self::zero(), $scale)) {
            // L'écart est réparti millime par millime, du poste le plus pondéré
            // au moins pondéré : résultat reproductible quel que soit l'ordre.
            $order = array_keys($positiveWeights);
            usort($order, static function ($left, $right) use ($positiveWeights): int {
                $comparison = self::compare($positiveWeights[$right], $positiveWeights[$left], 6);

                return 0 !== $comparison ? $comparison : strcmp((string) $left, (string) $right);
            });

            $step = self::compare($remainder, '0', $scale) > 0 ? '0.001' : '-0.001';
            $index = 0;
            while (0 !== self::compare($remainder, '0', $scale)) {
                $key = $order[$index % \count($order)];
                $shares[$key] = self::add($shares[$key], $step, $scale);
                $remainder = self::add($remainder, self::compare($step, '0') > 0 ? '-0.001' : '0.001', $scale);
                ++$index;
            }
        }

        return $shares;
    }

    /** Somme d'une liste de montants. */
    public static function sum(iterable $amounts, int $scale = self::SCALE): string
    {
        $total = '0.'.str_repeat('0', $scale);
        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) $amount, $scale);
        }

        return self::round($total, $scale);
    }

    public static function format(?string $amount, string $currency = 'TND', int $scale = self::SCALE): string
    {
        if (null === $amount) {
            return '—';
        }

        // Mise en forme à partir de la chaîne décimale : aucun passage par un
        // flottant, donc aucune perte de précision à l'affichage.
        $negative = str_starts_with($amount, '-');
        $value = self::round(ltrim($amount, '+-'), $scale);
        $parts = explode('.', $value, 2);
        $integerPart = ltrim($parts[0], '0');
        $integerPart = '' === $integerPart ? '0' : $integerPart;
        $fraction = $parts[1] ?? '';
        $grouped = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integerPart);

        return ($negative ? '-' : '').$grouped.($scale > 0 ? ','.$fraction : '').' '.$currency;
    }

    /** Taux de TVA exprimé en pourcentage lisible (19.0000 → 19 %). */
    public static function formatRate(?string $rate): string
    {
        if (null === $rate) {
            return '—';
        }

        $trimmed = rtrim(rtrim($rate, '0'), '.');

        return ('' === $trimmed ? '0' : $trimmed).' %';
    }
}
