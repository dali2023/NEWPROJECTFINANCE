<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Aperçu d'un fichier de prospects avant le choix explicite des colonnes.
 */
final readonly class ProspectImportPreview
{
    /**
     * @param list<string>       $columns    libellés affichés dans les listes de mapping
     * @param list<list<string>> $sampleRows trois premières lignes, pour contrôle visuel
     */
    public function __construct(
        public string $filename,
        public array $columns,
        public array $sampleRows,
        public int $totalRows,
    ) {
    }
}
