<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Génération des références métier (§4.1 : règles de numérotation).
 *
 * Format : PREFIXE-ANNEE-NUMERO, par exemple PRJ-2026-0001, FAC-2026-0042.
 *
 * L'unicité est garantie par un compteur atomique en base
 * (INSERT … ON CONFLICT DO UPDATE … RETURNING), ce qui évite tout doublon en cas
 * de création simultanée (§9.2, protection contre les modifications
 * concurrentes) sans verrou applicatif.
 */
final class ReferenceGenerator
{
    public const MODULE_PROJECT = 'PRJ';
    public const MODULE_INVOICE = 'FAC';
    public const MODULE_RECEIPT = 'ENC';
    public const MODULE_EXPENSE = 'DEP';
    public const MODULE_PAYMENT = 'PAI';
    public const MODULE_TRANSFER = 'TRF';
    public const MODULE_PARTNER = 'ASS';
    public const MODULE_CAMPAIGN = 'CAM';
    public const MODULE_TENDER = 'AO';
    public const MODULE_ASSET = 'EQP';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $timezone = 'Africa/Tunis',
    ) {
    }

    /**
     * Consomme et retourne le prochain numéro d'une séquence métier annuelle.
     */
    public function next(string $module, ?int $year = null, int $padding = 4): string
    {
        $year ??= $this->currentYear();
        $sequenceKey = sprintf('%s-%d', strtoupper($module), $year);

        $value = $this->entityManager->getConnection()->fetchOne(
            <<<'SQL'
                INSERT INTO reference_sequences (sequence_key, last_number, updated_at)
                VALUES (:key, 1, NOW())
                ON CONFLICT (sequence_key)
                DO UPDATE SET last_number = reference_sequences.last_number + 1, updated_at = NOW()
                RETURNING last_number
                SQL,
            ['key' => $sequenceKey],
        );

        return sprintf(
            '%s-%d-%s',
            strtoupper($module),
            $year,
            str_pad((string) $value, $padding, '0', STR_PAD_LEFT),
        );
    }

    /** Prévisualisation du format, sans consommer de numéro. */
    public function preview(string $module, ?int $year = null, int $padding = 4): string
    {
        return sprintf(
            '%s-%d-%s',
            strtoupper($module),
            $year ?? $this->currentYear(),
            str_pad('1', $padding, '0', STR_PAD_LEFT),
        );
    }

    private function currentYear(): int
    {
        return (int) (new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->format('Y');
    }
}
