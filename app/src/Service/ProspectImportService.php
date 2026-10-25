<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ProspectImportPreview;
use App\Dto\ProspectImportResult;
use App\Entity\Prospect;
use App\Entity\User;
use App\Enum\ProspectStatus;
use App\Repository\ProspectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Import Excel/CSV des prospects.
 *
 * Le parcours est volontairement en deux étapes :
 * 1. le fichier est déposé et analysé ;
 * 2. l'utilisateur associe explicitement chaque colonne à un champ métier.
 *
 * Les doublons sont mis à jour, jamais dupliqués : l'email est la clé
 * prioritaire, sinon le couple raison sociale + ville. Chaque ligne invalide est
 * signalée avec son numéro de ligne ; les lignes valides sont importées dans une
 * transaction unique.
 */
final class ProspectImportService
{
    private const SESSION_KEY = 'prospect_imports';
    private const MAX_ROWS = 5000;
    private const TOKEN_TTL = 86400;

    private const FIELDS = [
        'name',
        'contactName',
        'email',
        'phone',
        'taxIdentifier',
        'address',
        'city',
        'postalCode',
        'country',
        'source',
        'status',
        'estimatedAmountHt',
        'probability',
        'expectedCloseDate',
        'nextAction',
        'nextActionDate',
        'notes',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProspectRepository $prospectRepository,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly AuditLogger $auditLogger,
        private readonly RequestStack $requestStack,
        private readonly string $importDir,
    ) {
    }

    /**
     * Enregistre le fichier téléversé dans un répertoire privé et retourne un
     * jeton opaque, conservé en session.
     */
    public function createPendingImport(UploadedFile $file): string
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());
        if (!\in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw new \InvalidArgumentException('Formats acceptés : .xlsx, .xls et .csv.');
        }

        $directory = rtrim($this->importDir, '/\\');
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Le répertoire temporaire d’import est indisponible.');
        }

        $this->cleanupExpiredFiles($directory);

        $token = bin2hex(random_bytes(16));
        $filename = $token.'.'.$extension;
        $file->move($directory, $filename);

        $pending = $this->readPendingSession();
        $pending[$token] = [
            'filename' => $filename,
            'originalName' => $file->getClientOriginalName(),
            'createdAt' => time(),
        ];
        $this->requestStack->getSession()->set(self::SESSION_KEY, $pending);

        return $token;
    }

    /**
     * @return array{filename: string, originalName: string, createdAt: int, path: string, extension: string}|null
     */
    public function pendingImport(string $token): ?array
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        $pending = $this->readPendingSession();
        if (!isset($pending[$token])) {
            return null;
        }

        $entry = $pending[$token];
        $filename = basename((string) ($entry['filename'] ?? ''));
        $path = rtrim($this->importDir, '/\\').DIRECTORY_SEPARATOR.$filename;

        if ('' === $filename || !is_file($path)) {
            $this->discard($token);

            return null;
        }

        return [
            'filename' => $filename,
            'originalName' => (string) ($entry['originalName'] ?? $filename),
            'createdAt' => (int) ($entry['createdAt'] ?? 0),
            'path' => $path,
            'extension' => mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
        ];
    }

    public function discard(string $token): void
    {
        $pending = $this->readPendingSession();
        if (isset($pending[$token])) {
            $filename = basename((string) ($pending[$token]['filename'] ?? ''));
            if ('' !== $filename) {
                @unlink(rtrim($this->importDir, '/\\').DIRECTORY_SEPARATOR.$filename);
            }
            unset($pending[$token]);
            $this->requestStack->getSession()->set(self::SESSION_KEY, $pending);
        }
    }

    public function inspect(string $token): ProspectImportPreview
    {
        $pending = $this->pendingImport($token);
        if (null === $pending) {
            throw new \InvalidArgumentException('Cet import est introuvable ou a expiré. Veuillez déposer à nouveau le fichier.');
        }

        $workbook = $this->readWorkbook($pending['path'], $pending['extension']);
        $rows = $workbook['rows'];

        if ([] === $rows || $this->rowIsEmpty($rows[0])) {
            throw new \InvalidArgumentException('La première ligne du fichier doit contenir les en-têtes de colonnes.');
        }

        $dataRows = \count($rows) - 1;
        if ($dataRows < 1) {
            throw new \InvalidArgumentException('Le fichier ne contient aucune ligne de données après les en-têtes.');
        }
        if ($dataRows > self::MAX_ROWS) {
            throw new \InvalidArgumentException(sprintf('Le fichier dépasse la limite de %d lignes de données.', self::MAX_ROWS));
        }

        $columns = [];
        for ($index = 0; $index < $workbook['width']; ++$index) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $header = $this->cellToString($rows[0][$index] ?? null);
            $columns[$index] = $letter.('' !== $header ? ' · '.$header : ' · (sans en-tête)');
        }

        $sampleRows = [];
        foreach (\array_slice($rows, 1, 3) as $row) {
            $sample = [];
            for ($index = 0; $index < $workbook['width']; ++$index) {
                $sample[] = $this->cellToString($row[$index] ?? null);
            }
            if (!$this->rowIsEmpty($sample)) {
                $sampleRows[] = $sample;
            }
        }

        return new ProspectImportPreview(
            $pending['originalName'],
            $columns,
            $sampleRows,
            $dataRows,
        );
    }

    /**
     * @param array<int, string> $columns
     *
     * @return array<string, int|null>
     */
    public function guessMapping(array $columns): array
    {
        $mapping = array_fill_keys(self::FIELDS, null);

        $aliases = [
            'name' => ['raison sociale', 'societe', 'entreprise', 'nom du prospect', 'nom', 'client', 'organisme', 'prospect'],
            'contactName' => ['contact principal', 'nom du contact', 'contact', 'interlocuteur'],
            'email' => ['adresse electronique', 'adresse email', 'email', 'e mail', 'courriel', 'mail'],
            'phone' => ['telephone', 'tel', 'mobile', 'gsm', 'phone'],
            'taxIdentifier' => ['identifiant fiscal', 'matricule fiscal', 'tax id', 'siret', 'nif', 'mf'],
            'address' => ['adresse postale', 'adresse', 'address'],
            'city' => ['ville', 'localite', 'city'],
            'postalCode' => ['code postal', 'postal', 'zip', 'cp'],
            'country' => ['pays', 'country'],
            'source' => ['source', 'origine', 'provenance'],
            'status' => ['statut', 'status', 'etat'],
            'estimatedAmountHt' => ['montant estime ht', 'montant estime', 'amount ht', 'montant', 'budget', 'potentiel', 'valeur', 'amount'],
            'probability' => ['probabilite', 'probability', 'chance', 'pourcentage'],
            'expectedCloseDate' => ['date de conclusion prevue', 'date conclusion', 'date de cloture', 'date cloture', 'date signature', 'closing date', 'echeance'],
            'nextActionDate' => ['date de la prochaine action', 'date prochaine action', 'date action', 'next action date', 'rappel'],
            'nextAction' => ['prochaine action', 'next action', 'action'],
            'notes' => ['commentaires', 'commentaire', 'observations', 'remarques', 'notes'],
        ];

        $normalizedHeaders = [];
        foreach ($columns as $index => $label) {
            $header = str_contains($label, '·') ? trim(explode('·', $label, 2)[1]) : $label;
            $normalizedHeaders[$index] = $this->normalizeHeader($header);
        }

        // Une correspondance exacte est toujours préférée à une correspondance partielle.
        foreach (['exact', 'partial'] as $mode) {
            foreach ($aliases as $field => $fieldAliases) {
                if (null !== $mapping[$field]) {
                    continue;
                }
                foreach ($normalizedHeaders as $index => $header) {
                    if ('' === $header || \in_array($index, $mapping, true)) {
                        continue;
                    }
                    foreach ($fieldAliases as $alias) {
                        $matches = 'exact' === $mode ? $header === $alias : str_contains($header, $alias);
                        if ($matches) {
                            $mapping[$field] = $index;
                            continue 2;
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * @param array<string, int|null> $mapping
     */
    public function import(string $token, array $mapping, User $actor): ProspectImportResult
    {
        $pending = $this->pendingImport($token);
        if (null === $pending) {
            throw new \InvalidArgumentException('Cet import est introuvable ou a expiré. Veuillez déposer à nouveau le fichier.');
        }

        $mapping = $this->normalizeMapping($mapping);
        if (null === $mapping['name']) {
            throw new \InvalidArgumentException('La colonne correspondant à la raison sociale est obligatoire.');
        }

        $workbook = $this->readWorkbook($pending['path'], $pending['extension']);
        $rows = $workbook['rows'];
        if (\count($rows) < 2) {
            throw new \InvalidArgumentException('Le fichier ne contient aucune ligne de données.');
        }
        if (\count($rows) - 1 > self::MAX_ROWS) {
            throw new \InvalidArgumentException(sprintf('Le fichier dépasse la limite de %d lignes de données.', self::MAX_ROWS));
        }

        $validRows = [];
        $errors = [];
        $skipped = 0;
        foreach (\array_slice($rows, 1) as $offset => $row) {
            $rowNumber = $offset + 2;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            [$data, $rowErrors] = $this->parseRow($row, $mapping);
            if ([] !== $rowErrors) {
                ++$skipped;
                foreach ($rowErrors as $message) {
                    $errors[] = ['row' => $rowNumber, 'message' => $message];
                }
                continue;
            }

            $validRows[] = ['row' => $rowNumber, 'data' => $data];
        }

        if ([] === $validRows) {
            usort($errors, static fn (array $left, array $right): int => $left['row'] <=> $right['row']);

            return new ProspectImportResult(0, 0, $skipped, $errors);
        }

        return $this->entityManager->wrapInTransaction(function () use ($validRows, $actor, $errors, $skipped): ProspectImportResult {
            $created = 0;
            $updated = 0;
            $transactionErrors = [];
            $skippedInTransaction = 0;

            /** @var array<string, Prospect> $byEmail */
            $byEmail = [];
            /** @var array<string, Prospect> $ByNameCity */
            $ByNameCity = [];

            foreach ($validRows as $entry) {
                /** @var array<string, mixed> $data */
                $data = $entry['data'];
                $rowNumber = $entry['row'];

                $emailKey = null !== $data['email'] && '' !== (string) $data['email']
                    ? mb_strtolower((string) $data['email'])
                    : null;
                $nameCityKey = $this->nameCityKey((string) $data['name'], null !== $data['city'] ? (string) $data['city'] : null);

                $prospect = null;
                if (null !== $emailKey && isset($byEmail[$emailKey])) {
                    $prospect = $byEmail[$emailKey];
                } elseif (null === $emailKey && isset($ByNameCity[$nameCityKey])) {
                    $prospect = $ByNameCity[$nameCityKey];
                }

                if (null === $prospect) {
                    $prospect = $this->prospectRepository->findDuplicate(
                        null !== $data['email'] ? (string) $data['email'] : null,
                        (string) $data['name'],
                        null !== $data['city'] ? (string) $data['city'] : null,
                    );
                }

                // Une ligne précédente du même fichier peut avoir créé le
                // prospect : il n'est pas encore visible par une requête SQL.
                if (null === $prospect && isset($ByNameCity[$nameCityKey])) {
                    $prospect = $ByNameCity[$nameCityKey];
                }

                if (null !== $prospect) {
                    if ($prospect->isConverted()) {
                        ++$skippedInTransaction;
                        $transactionErrors[] = [
                            'row' => $rowNumber,
                            'message' => sprintf('Le prospect existant %s est déjà converti et ne peut pas être modifié par un import.', $prospect->getReference()),
                        ];
                        continue;
                    }

                    $this->removeFromIndexes($prospect, $byEmail, $ByNameCity);
                    $this->applyData($prospect, $data, $actor);
                    ++$updated;
                } else {
                    $prospect = new Prospect();
                    $prospect->setReference($this->referenceGenerator->next(ReferenceGenerator::MODULE_PROSPECT))
                        ->setOwner($actor)
                        ->setCreatedBy($actor);
                    $this->applyData($prospect, $data, $actor);
                    $this->entityManager->persist($prospect);
                    ++$created;
                }

                $this->addToIndexes($prospect, $byEmail, $ByNameCity);
            }

            $this->entityManager->flush();

            $allErrors = array_merge($errors, $transactionErrors);
            usort($allErrors, static fn (array $left, array $right): int => $left['row'] <=> $right['row']);

            $result = new ProspectImportResult(
                $created,
                $updated,
                $skipped + $skippedInTransaction,
                $allErrors,
            );

            $this->auditLogger->log(
                'import',
                'Prospect',
                null,
                null,
                [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $result->skipped,
                ],
                'success',
                'M03',
                sprintf(
                    'Import Excel — %d créé(s), %d mis à jour, %d ligne(s) ignorée(s)',
                    $created,
                    $updated,
                    $result->skipped,
                ),
                flush: false,
            );

            return $result;
        });
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int|null> $mapping
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function parseRow(array $row, array $mapping): array
    {
        $data = array_fill_keys(self::FIELDS, null);
        $errors = [];

        $stringFields = [
            'name' => ['Raison sociale', 200],
            'contactName' => ['Contact principal', 160],
            'phone' => ['Téléphone', 40],
            'taxIdentifier' => ['Identifiant fiscal', 60],
            'address' => ['Adresse', 255],
            'city' => ['Ville', 120],
            'postalCode' => ['Code postal', 20],
            'country' => ['Pays', 80],
            'source' => ['Source', 80],
            'nextAction' => ['Prochaine action', 255],
        ];

        foreach ($stringFields as $field => [$label, $maxLength]) {
            $value = $this->valueFor($row, $mapping, $field);
            $string = $this->cellToString($value);
            if ('' === $string) {
                if ('name' === $field) {
                    $errors[] = 'La raison sociale est obligatoire.';
                }
                continue;
            }
            if (mb_strlen($string) > $maxLength) {
                $errors[] = sprintf('%s : %d caractères maximum.', $label, $maxLength);
                continue;
            }
            $data[$field] = $string;
        }

        $email = $this->cellToString($this->valueFor($row, $mapping, 'email'));
        if ('' !== $email) {
            if (mb_strlen($email) > 180 || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse électronique invalide.';
            } else {
                $data['email'] = mb_strtolower($email);
            }
        }

        $statusValue = $this->cellToString($this->valueFor($row, $mapping, 'status'));
        if ('' !== $statusValue) {
            $status = ProspectStatus::tryFromImport($statusValue);
            if (null === $status) {
                $errors[] = sprintf('Statut inconnu : « %s ». Valeurs acceptées : Nouveau, Qualifié, En discussion, Gagné, Perdu.', $statusValue);
            } elseif (ProspectStatus::Converted === $status) {
                $errors[] = 'Le statut « Converti » ne peut pas être importé : utilisez la conversion depuis la fiche prospect.';
            } else {
                $data['status'] = $status;
            }
        }

        try {
            $amount = $this->valueFor($row, $mapping, 'estimatedAmountHt');
            if (null !== $amount && '' !== $this->cellToString($amount)) {
                $data['estimatedAmountHt'] = $this->parseAmount($amount);
            }
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }

        try {
            $probability = $this->valueFor($row, $mapping, 'probability');
            if (null !== $probability && '' !== $this->cellToString($probability)) {
                $data['probability'] = $this->parseProbability($probability);
            }
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }

        foreach (['expectedCloseDate' => 'Date de conclusion prévue', 'nextActionDate' => 'Date de la prochaine action'] as $field => $label) {
            try {
                $value = $this->valueFor($row, $mapping, $field);
                if (null !== $value && '' !== $this->cellToString($value)) {
                    $data[$field] = $this->parseDate($value, $label);
                }
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $notes = $this->valueFor($row, $mapping, 'notes');
        if (null !== $notes) {
            $notesString = trim((string) $this->cellToString($notes, true));
            if ('' !== $notesString) {
                $data['notes'] = $notesString;
            }
        }

        return [$data, $errors];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyData(Prospect $prospect, array $data, User $actor): void
    {
        $prospect->setName((string) $data['name']);

        if (null !== $data['contactName']) {
            $prospect->setContactName((string) $data['contactName']);
        }
        if (null !== $data['email']) {
            $prospect->setEmail((string) $data['email']);
        }
        if (null !== $data['phone']) {
            $prospect->setPhone((string) $data['phone']);
        }
        if (null !== $data['taxIdentifier']) {
            $prospect->setTaxIdentifier((string) $data['taxIdentifier']);
        }
        if (null !== $data['address']) {
            $prospect->setAddress((string) $data['address']);
        }
        if (null !== $data['city']) {
            $prospect->setCity((string) $data['city']);
        }
        if (null !== $data['postalCode']) {
            $prospect->setPostalCode((string) $data['postalCode']);
        }
        if (null !== $data['country']) {
            $prospect->setCountry((string) $data['country']);
        }
        if (null !== $data['source']) {
            $prospect->setSource((string) $data['source']);
        }
        if (null !== $data['estimatedAmountHt']) {
            $prospect->setEstimatedAmountHt((string) $data['estimatedAmountHt']);
        }
        if (null !== $data['probability']) {
            $prospect->setProbability((int) $data['probability']);
        }
        if (null !== $data['expectedCloseDate']) {
            $prospect->setExpectedCloseDate($data['expectedCloseDate']);
        }
        if (null !== $data['nextAction']) {
            $prospect->setNextAction((string) $data['nextAction']);
        }
        if (null !== $data['nextActionDate']) {
            $prospect->setNextActionDate($data['nextActionDate']);
        }
        if (null !== $data['notes']) {
            $prospect->setNotes((string) $data['notes']);
        }
        if (null !== $data['status']) {
            $prospect->setStatus($data['status']);
        }
        if (null === $prospect->getOwner()) {
            $prospect->setOwner($actor);
        }
    }

    /**
     * @param array<string, int|null> $mapping
     *
     * @return array<string, int|null>
     */
    private function normalizeMapping(array $mapping): array
    {
        $normalized = array_fill_keys(self::FIELDS, null);
        $used = [];

        foreach (self::FIELDS as $field) {
            $value = $mapping[$field] ?? null;
            if (null === $value || '' === $value) {
                continue;
            }

            $index = (int) $value;
            if (isset($used[$index])) {
                throw new \InvalidArgumentException(sprintf('La colonne %s est associée à plusieurs champs. Chaque colonne ne peut être utilisée qu’une fois.', Coordinate::stringFromColumnIndex($index + 1)));
            }
            $used[$index] = true;
            $normalized[$field] = $index;
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int|null> $mapping
     */
    private function valueFor(array $row, array $mapping, string $field): mixed
    {
        $index = $mapping[$field] ?? null;

        return null === $index ? null : ($row[$index] ?? null);
    }

    /**
     * @return array{rows: list<array<int, mixed>>, width: int, sheetTitle: string}
     */
    private function readWorkbook(string $path, string $extension): array
    {
        try {
            if ('csv' === $extension) {
                $sample = (string) file_get_contents($path, false, null, 0, 8192);
                $encoding = mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'Windows-1252';
                $reader = new Csv();
                $reader->setInputEncoding($encoding);
                $reader->setDelimiter($this->detectDelimiter($sample));
                $reader->setReadDataOnly(true);
            } else {
                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
            }

            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Le fichier n’a pas pu être lu. Vérifiez qu’il s’agit d’un classeur Excel ou CSV valide.');
        }

        $rows = [];
        $sheetTitle = '';

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheetTitle = $sheet->getTitle();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();

            if ($highestRow < 1) {
                return ['rows' => [], 'width' => 0, 'sheetTitle' => $sheetTitle];
            }

            /** @var list<array<int, mixed>> $rows */
            $rows = $sheet->rangeToArray(
                sprintf('A1:%s%d', $highestColumn, $highestRow),
                null,
                true,
                false,
                false,
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        while ([] !== $rows && $this->rowIsEmpty($rows[\count($rows) - 1])) {
            array_pop($rows);
        }

        $width = 0;
        foreach ($rows as $row) {
            $width = max($width, \count($row));
        }
        if ($width > 0) {
            foreach ($rows as &$row) {
                $row = array_pad($row, $width, null);
            }
            unset($row);
        }

        return [
            'rows' => $rows,
            'width' => $width,
            'sheetTitle' => $sheetTitle,
        ];
    }

    private function detectDelimiter(string $sample): string
    {
        $firstLine = strtok($sample, "\r\n");
        if (false === $firstLine) {
            return ';';
        }

        $counts = [
            ';' => substr_count($firstLine, ';'),
            ',' => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
            '|' => substr_count($firstLine, '|'),
        ];
        arsort($counts);

        $delimiter = array_key_first($counts);

        return 0 === $counts[$delimiter] ? ';' : (string) $delimiter;
    }

    private function cellToString(mixed $value, bool $multiline = false): string
    {
        if (null === $value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if (\is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (\is_float($value)) {
            $value = floor($value) === $value ? sprintf('%.0f', $value) : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        $string = trim((string) $value);
        $string = (string) preg_replace('/^\x{FEFF}/u', '', $string);

        return $multiline ? $string : (string) preg_replace('/\s+/u', ' ', $string);
    }

    /**
     * @param array<int, mixed> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ('' !== $this->cellToString($value)) {
                return false;
            }
        }

        return true;
    }

    private function parseAmount(mixed $value): string
    {
        $raw = $this->cellToString($value);
        $clean = (string) preg_replace('/[^0-9,.\-]/u', '', $raw);

        try {
            $amount = Money::normalize($clean);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(sprintf('Montant estimé invalide : « %s ».', $raw));
        }

        if (Money::isNegative($amount)) {
            throw new \InvalidArgumentException('Le montant estimé ne peut pas être négatif.');
        }

        return $amount;
    }

    private function parseProbability(mixed $value): int
    {
        $raw = $this->cellToString($value);
        $clean = str_replace(',', '.', (string) preg_replace('/[^0-9,.\-]/u', '', $raw));

        if (1 !== preg_match('/^-?\d+(\.\d+)?$/', $clean)) {
            throw new \InvalidArgumentException(sprintf('Probabilité invalide : « %s ».', $raw));
        }

        $probability = (int) round((float) $clean);
        if ($probability < 0 || $probability > 100) {
            throw new \InvalidArgumentException('La probabilité doit être comprise entre 0 et 100 %.');
        }

        return $probability;
    }

    private function parseDate(mixed $value, string $label): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format('Y-m-d'));
        }

        $raw = $this->cellToString($value);
        if ('' === $raw) {
            throw new \InvalidArgumentException(sprintf('%s : date vide.', $label));
        }

        if (1 === preg_match('/^\d{8}$/', $raw)) {
            $date = $this->createDate('Ymd', $raw);
            if (null !== $date) {
                return $date;
            }
        }

        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial > 0) {
                try {
                    $date = ExcelDate::excelToDateTimeObject($serial, new \DateTimeZone('Africa/Tunis'));

                    return new \DateTimeImmutable($date->format('Y-m-d'));
                } catch (\Throwable) {
                    // On tente ensuite les formats texte ci-dessous.
                }
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'm/d/Y'] as $format) {
            $date = $this->createDate($format, $raw);
            if (null !== $date) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('%s : date invalide « %s » (formats attendus : jj/mm/aaaa ou aaaa-mm-jj).', $label, $raw));
    }

    private function createDate(string $format, string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (false === $date || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        if (false !== $transliterated) {
            $header = $transliterated;
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $header));
    }

    private function nameCityKey(string $name, ?string $city): string
    {
        return mb_strtolower(trim($name)).'|'.mb_strtolower(trim((string) $city));
    }

    private function emailKey(Prospect $prospect): ?string
    {
        $email = $prospect->getEmail();

        return null === $email || '' === $email ? null : mb_strtolower($email);
    }

    /**
     * @param array<string, Prospect> $byEmail
     * @param array<string, Prospect> $ByNameCity
     */
    private function addToIndexes(Prospect $prospect, array &$byEmail, array &$ByNameCity): void
    {
        $emailKey = $this->emailKey($prospect);
        if (null !== $emailKey) {
            $byEmail[$emailKey] = $prospect;
        }
        $ByNameCity[$this->nameCityKey($prospect->getName(), $prospect->getCity())] = $prospect;
    }

    /**
     * @param array<string, Prospect> $byEmail
     * @param array<string, Prospect> $ByNameCity
     */
    private function removeFromIndexes(Prospect $prospect, array &$byEmail, array &$ByNameCity): void
    {
        $emailKey = $this->emailKey($prospect);
        if (null !== $emailKey && ($byEmail[$emailKey] ?? null) === $prospect) {
            unset($byEmail[$emailKey]);
        }
        $nameCityKey = $this->nameCityKey($prospect->getName(), $prospect->getCity());
        if (($ByNameCity[$nameCityKey] ?? null) === $prospect) {
            unset($ByNameCity[$nameCityKey]);
        }
    }

    /**
     * @return array<string, array{filename: string, originalName: string, createdAt: int}>
     */
    private function readPendingSession(): array
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, []);
    }

    private function cleanupExpiredFiles(string $directory): void
    {
        $pending = $this->readPendingSession();
        $now = time();
        foreach ($pending as $token => $entry) {
            if ($now - (int) ($entry['createdAt'] ?? 0) <= self::TOKEN_TTL) {
                continue;
            }
            $filename = basename((string) ($entry['filename'] ?? ''));
            if ('' !== $filename) {
                @unlink($directory.DIRECTORY_SEPARATOR.$filename);
            }
            unset($pending[$token]);
        }
        $this->requestStack->getSession()->set(self::SESSION_KEY, $pending);

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (!is_file($file) || $now - (int) filemtime($file) <= self::TOKEN_TTL) {
                continue;
            }
            if (1 === preg_match('/^[a-f0-9]{32}\.(xlsx|xls|csv)$/', basename($file))) {
                @unlink($file);
            }
        }
    }
}
