<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Export\Column;
use App\Service\Export\SpreadsheetExporter;
use PHPUnit\Framework\TestCase;

/**
 * REC-037 — l'export Excel produit un véritable classeur .xlsx.
 */
final class SpreadsheetExporterTest extends TestCase
{
    public function testDownloadBuildsAValidWorkbookWithTotals(): void
    {
        $exporter = new SpreadsheetExporter();

        $response = $exporter->download(
            'Clients exportés',
            'Liste des clients',
            [
                Column::text('Référence', 18),
                Column::text('Client', 30),
                Column::money('Montant HT'),
                Column::date('Créé le'),
            ],
            [
                ['007', 'Client Alpha', '12.345', new \DateTimeImmutable('2026-01-15')],
                ['008', 'Client Bêta', '3.000', null],
            ],
            ['Recherche : alpha', '2 client(s)'],
        );

        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        self::assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('clients-exportes_', (string) $response->headers->get('Content-Disposition'));

        $binary = (string) $response->getContent();
        self::assertStringStartsWith('PK', $binary, 'Un fichier .xlsx est une archive ZIP.');

        $path = tempnam(sys_get_temp_dir(), 'one-erp-xlsx-');
        self::assertNotFalse($path);
        file_put_contents($path, $binary);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));

            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $sharedStrings = (string) $zip->getFromName('xl/sharedStrings.xml');
            $zip->close();

            self::assertNotSame('', $sheet);
            self::assertStringContainsString('Liste des clients', $sharedStrings);
            self::assertStringContainsString('Référence', $sharedStrings);
            self::assertStringContainsString('Client Alpha', $sharedStrings);
            // Les références restent du texte : les zéros de tête sont conservés.
            self::assertStringContainsString('007', $sharedStrings);
            self::assertStringContainsString('TOTAL', $sharedStrings);
            // 12.345 + 3.000 = 15.345, écrit comme valeur numérique.
            self::assertStringContainsString('15.345', $sheet);
            self::assertStringContainsString('<autoFilter', $sheet);
            self::assertStringContainsString('<pane', $sheet);
        } finally {
            @unlink($path);
        }
    }

    private function streamContent(\Symfony\Component\HttpFoundation\Response $response): string
    {
        return (string) $response->getContent();
    }
}
