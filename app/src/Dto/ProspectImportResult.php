<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Résultat détaillé d'un import de prospects.
 */
final class ProspectImportResult
{
    /**
     * @param list<array{row: int, message: string}> $errors
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {
    }

    public function importedCount(): int
    {
        return $this->created + $this->updated;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
