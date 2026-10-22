<?php

declare(strict_types=1);

namespace App\Service\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Génère les exports Excel (.xlsx) de l'application.
 *
 * Le classeur contient un en-tête imprimable (titre, contexte, date), une ligne
 * de titres figée et filtrable, des colonnes dimensionnées, des formats de
 * nombres explicites et, si demandé, une ligne de totaux.
 */
final class SpreadsheetExporter
{
    private const BRAND = '0B5C56';
    private const BRAND_LIGHT = 'E7F3F1';
    private const MUTED = '667085';

    /**
     * @param list<Column>                     $columns
     * @param iterable<array<int, mixed>>      $rows
     * @param list<string>                     $context
     */
    public function download(
        string $filename,
        string $title,
        array $columns,
        iterable $rows,
        array $context = [],
    ): Response {
        if ([] === $columns) {
            throw new \InvalidArgumentException('Un export doit contenir au moins une colonne.');
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('ONE ERP Finance & Project Control')
            ->setTitle($title)
            ->setDescription(implode(' — ', $context));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($title));

        $lastIndex = \count($columns);
        $lastColumn = Coordinate::stringFromColumnIndex($lastIndex);

        // Bandeau de titre.
        $sheet->mergeCells(sprintf('A1:%s1', $lastColumn));
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF'.self::BRAND);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Contexte (filtres, date d'édition).
        $context[] = 'Édité le '.date('d/m/Y à H:i');
        $sheet->mergeCells(sprintf('A2:%s2', $lastColumn));
        $sheet->setCellValue('A2', implode('  ·  ', array_filter($context)));
        $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF'.self::MUTED);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // En-têtes.
        $headerRow = 3;
        foreach ($columns as $index => $column) {
            $sheet->setCellValue([$index + 1, $headerRow], $column->label);
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($column->width);
        }

        $sheet->getStyle(sprintf('A%d:%s%d', $headerRow, $lastColumn, $headerRow))->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.self::BRAND]],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'wrapText' => true,
            ],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF'.self::BRAND]]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        // Données.
        $rowNumber = $headerRow + 1;
        $hasTotals = false;
        $totals = array_fill(0, $lastIndex, '0');
        foreach ($rows as $row) {
            $row = array_values($row);
            foreach ($columns as $index => $column) {
                $value = $row[$index] ?? null;
                $coordinate = [$index + 1, $rowNumber];
                $this->writeValue($sheet, $coordinate, $column, $value);

                if ($column->total) {
                    $hasTotals = true;
                    if ('integer' === $column->type) {
                        $totals[$index] = (string) ((int) $totals[$index] + (int) $value);
                    } else {
                        $scale = 'rate' === $column->type ? 4 : ('decimal' === $column->type ? 4 : 3);
                        $totals[$index] = bcadd($totals[$index], (string) ($value ?? '0'), $scale);
                    }
                }
            }
            $rowNumber++;
        }

        $lastDataRow = max($headerRow, $rowNumber - 1);

        if ($lastDataRow > $headerRow) {
            $sheet->getStyle(sprintf('A%d:%s%d', $headerRow + 1, $lastColumn, $lastDataRow))->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFE4E7EC']],
                ],
            ]);

            // Lignes alternées, plus lisibles à l'écran comme à l'impression.
            for ($line = $headerRow + 2; $line <= $lastDataRow; $line += 2) {
                $sheet->getStyle(sprintf('A%d:%s%d', $line, $lastColumn, $line))
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFF7FAF9');
            }
        }

        if ($hasTotals) {
            $totalRow = $lastDataRow + 1;
            $sheet->setCellValue([1, $totalRow], 'TOTAL');
            foreach ($columns as $index => $column) {
                if (!$column->total) {
                    continue;
                }
                $cell = $sheet->getCell([$index + 1, $totalRow]);
                $cell->setValue((float) $totals[$index]);
                if (null !== $column->format) {
                    $cell->getStyle()->getNumberFormat()->setFormatCode($column->format);
                }
            }
            $sheet->getStyle(sprintf('A%d:%s%d', $totalRow, $lastColumn, $totalRow))->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF'.self::BRAND]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.self::BRAND_LIGHT]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF'.self::BRAND]]],
            ]);
        }

        $sheet->freezePane('A4');
        $sheet->setAutoFilter(sprintf('A%d:%s%d', $headerRow, $lastColumn, $headerRow));

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setOrientation($lastIndex > 6 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setFitToPage(true);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageMargins()->setTop(0.55)->setBottom(0.55)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&LONE ERP — '.$title.'&RPage &P / &N');

        $filename = $this->filename($filename);

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $temporaryFile = tempnam(sys_get_temp_dir(), 'one-erp-xlsx-');
        if (false === $temporaryFile) {
            throw new \RuntimeException('Impossible de générer le fichier Excel.');
        }

        try {
            $writer->save($temporaryFile);
            $content = file_get_contents($temporaryFile);
            if (false === $content) {
                throw new \RuntimeException('Impossible de lire le fichier Excel généré.');
            }
        } finally {
            @unlink($temporaryFile);
            $spreadsheet->disconnectWorksheets();
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                $filename,
            ),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param array{0: int, 1: int} $coordinate
     */
    private function writeValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $coordinate, Column $column, mixed $value): void
    {
        $cell = $sheet->getCell($coordinate);

        if ($value instanceof \DateTimeInterface) {
            $cell->setValue(ExcelDate::PHPToExcel($value));
        } elseif (\is_bool($value)) {
            $cell->setValue($value ? 'Oui' : 'Non');
        } elseif (null === $value || '' === $value) {
            $cell->setValue('');
        } elseif ('money' === $column->type || 'rate' === $column->type || 'decimal' === $column->type) {
            $cell->setValue((float) $value);
        } elseif ('integer' === $column->type) {
            $cell->setValue((int) $value);
        } else {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }

        if (null !== $column->format) {
            $cell->getStyle()->getNumberFormat()->setFormatCode($column->format);
        }
    }

    private function sheetTitle(string $title): string
    {
        $title = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title);
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        return '' === $title ? 'Export' : mb_substr($title, 0, 31);
    }

    private function filename(string $basename): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $basename) ?: $basename;
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii), '-'));
        $slug = '' === $slug ? 'export' : $slug;

        return sprintf('%s_%s.xlsx', $slug, date('Ymd_His'));
    }
}
