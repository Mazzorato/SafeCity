<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Valide et enregistre localement les photos jointes aux signalements.
 */
final class FileUploader
{
    private const PHOTO_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private string $quarantineDirectory;

    public function __construct(
        private string $targetDirectory,
        private SluggerInterface $slugger,
        ?string $quarantineDirectory = null,
    ) {
        // Le stockage configuré est public/uploads/photos : remonter de trois
        // niveaux place la quarantaine dans var, donc hors de l’accès HTTP.
        $this->quarantineDirectory = $quarantineDirectory
            ?? dirname($this->targetDirectory, 3) . DIRECTORY_SEPARATOR . 'var'
                . DIRECTORY_SEPARATOR . 'quarantine' . DIRECTORY_SEPARATOR . 'photos';
    }

    public function upload(UploadedFile $file): string
    {
        $this->ensureTargetDirectory();

        $mimeType = $file->getMimeType();
        $extension = self::PHOTO_EXTENSIONS[$mimeType] ?? null;
        if ($extension === null) {
            throw new \RuntimeException('Le format réel de la photo n’est pas pris en charge.');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = strtolower((string) $this->slugger->slug($originalFilename));
        if ($safeFilename === '') {
            $safeFilename = 'photo';
        }
        $newFilename = $safeFilename . '-' . bin2hex(random_bytes(12)) . '.' . $extension;

        try {
            $file->move($this->targetDirectory, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Impossible d’enregistrer la photo localement.', previous: $e);
        }

        return $newFilename;
    }

    public function remove(string $filename): void
    {
        $path = $this->targetDirectory . DIRECTORY_SEPARATOR . basename($filename);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Impossible de nettoyer une photo incomplète.');
        }
    }

    /**
     * Déplace atomiquement une photo hors du dossier public avant une suppression en base.
     */
    public function quarantine(string $filename): bool
    {
        $filename = basename($filename);
        $sourcePath = $this->targetDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($sourcePath)) {
            return false;
        }

        $this->ensureQuarantineDirectory();
        $quarantinePath = $this->quarantineDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($quarantinePath)) {
            throw new \RuntimeException(sprintf('La photo « %s » est déjà en quarantaine.', $filename));
        }
        if (!rename($sourcePath, $quarantinePath)) {
            throw new \RuntimeException(sprintf('Impossible de mettre la photo « %s » en quarantaine.', $filename));
        }

        return true;
    }

    /**
     * Replace une photo lorsque la transaction Doctrine n’a pas abouti.
     */
    public function restoreQuarantined(string $filename): void
    {
        $filename = basename($filename);
        $quarantinePath = $this->quarantineDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($quarantinePath)) {
            return;
        }

        $this->ensureTargetDirectory();
        $targetPath = $this->targetDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($targetPath)) {
            throw new \RuntimeException(sprintf('La restauration de « %s » écraserait une photo existante.', $filename));
        }
        if (!rename($quarantinePath, $targetPath)) {
            throw new \RuntimeException(sprintf('Impossible de restaurer la photo « %s ».', $filename));
        }

        $this->removeEmptyQuarantineDirectory();
    }

    /**
     * Supprime une photo mise en quarantaine après validation de la transaction.
     */
    public function removeQuarantined(string $filename): void
    {
        $path = $this->quarantineDirectory . DIRECTORY_SEPARATOR . basename($filename);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Impossible de supprimer définitivement une photo en quarantaine.');
        }

        $this->removeEmptyQuarantineDirectory();
    }

    /**
     * @return string[]
     */
    public function quarantinedFilenames(): array
    {
        if (!is_dir($this->quarantineDirectory)) {
            return [];
        }

        $filenames = [];
        foreach (scandir($this->quarantineDirectory) ?: [] as $entry) {
            if (!in_array($entry, ['.', '..'], true)
                && is_file($this->quarantineDirectory . DIRECTORY_SEPARATOR . $entry)
            ) {
                $filenames[] = basename($entry);
            }
        }
        sort($filenames);

        return $filenames;
    }

    private function ensureTargetDirectory(): void
    {
        if (!is_dir($this->targetDirectory)
            && !mkdir($this->targetDirectory, 0775, true)
            && !is_dir($this->targetDirectory)
        ) {
            throw new \RuntimeException('Impossible de créer le dossier local des photos.');
        }
    }

    private function ensureQuarantineDirectory(): void
    {
        if (!is_dir($this->quarantineDirectory)
            && !mkdir($this->quarantineDirectory, 0775, true)
            && !is_dir($this->quarantineDirectory)
        ) {
            throw new \RuntimeException('Impossible de créer la quarantaine locale des photos.');
        }
    }

    private function removeEmptyQuarantineDirectory(): void
    {
        if (!is_dir($this->quarantineDirectory)) {
            return;
        }

        $remainingEntries = array_diff(scandir($this->quarantineDirectory) ?: [], ['.', '..']);
        if ($remainingEntries === [] && !rmdir($this->quarantineDirectory)) {
            throw new \RuntimeException('Impossible de retirer le dossier de quarantaine vide.');
        }
    }
}


