<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stockage des justificatifs (§5.2 Z, §9.2, §9.5).
 *
 * Les fichiers sont écrits dans un répertoire paramétrable situé hors du
 * répertoire public : aucun accès direct par URL, la restitution passe
 * obligatoirement par un contrôleur qui vérifie les permissions.
 */
final class DocumentStorage
{
    public function __construct(private readonly string $storageDir)
    {
    }

    /**
     * Enregistre un fichier et retourne son chemin relatif au répertoire de
     * stockage (jamais un chemin absolu exposable).
     */
    public function store(UploadedFile $file, string $subDirectory = 'documents'): string
    {
        $safeSubDirectory = trim(preg_replace('/[^a-zA-Z0-9_\/-]/', '', $subDirectory) ?? 'documents', '/');
        $targetDirectory = rtrim($this->storageDir, '/\\').'/'.$safeSubDirectory;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0770, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le répertoire de stockage "%s".', $targetDirectory));
        }

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $fileName = bin2hex(random_bytes(16)).'.'.$extension;

        $file->move($targetDirectory, $fileName);

        return $safeSubDirectory.'/'.$fileName;
    }

    public function absolutePath(string $relativePath): string
    {
        // Protection contre les traversées de répertoire (../../).
        $normalized = str_replace('\\', '/', $relativePath);
        if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            throw new \InvalidArgumentException('Chemin de document invalide.');
        }

        return rtrim($this->storageDir, '/\\').'/'.ltrim($normalized, '/');
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolutePath($relativePath));
    }

    public function delete(string $relativePath): void
    {
        $path = $this->absolutePath($relativePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function file(string $relativePath): File
    {
        return new File($this->absolutePath($relativePath));
    }
}
