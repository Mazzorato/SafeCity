<?php

namespace App\Tests\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Vérifie les formats réellement acceptés et le nettoyage des photos temporaires.
 */
final class MediaUploaderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        if (!extension_loaded('fileinfo')) {
            self::markTestSkipped('L’extension PHP fileinfo est nécessaire aux tests de médias.');
        }

        $this->temporaryDirectory = \dirname(__DIR__, 2) . '/var/tests/media-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!isset($this->temporaryDirectory) || !is_dir($this->temporaryDirectory)) {
            return;
        }

        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (['photos'] as $directory) {
            $path = $this->temporaryDirectory . '/' . $directory;
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        rmdir($this->temporaryDirectory);
    }

    public function testPhotoUploadUsesDetectedMimeTypeAndCanBeRemoved(): void
    {
        $source = $this->createSourceFile(
            'photo-source',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true
            )
        );
        $uploader = new FileUploader($this->temporaryDirectory . '/photos', new AsciiSlugger());

        $filename = $uploader->upload(new UploadedFile($source, 'Éclairage public.png', test: true));

        self::assertMatchesRegularExpression('/^eclairage-public-[a-f0-9]{24}\.png$/', $filename);
        self::assertFileExists($this->temporaryDirectory . '/photos/' . $filename);

        $uploader->remove($filename);

        self::assertFileDoesNotExist($this->temporaryDirectory . '/photos/' . $filename);
    }

    public function testUnsupportedContentIsRejected(): void
    {
        $source = $this->createSourceFile('invalid-source', 'contenu non multimédia');
        $uploader = new FileUploader($this->temporaryDirectory . '/photos', new AsciiSlugger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le format réel de la photo n’est pas pris en charge.');

        $uploader->upload(new UploadedFile($source, 'fausse-photo.jpg', test: true));
    }

    public function testPhotoCanBeQuarantinedRestoredAndPurged(): void
    {
        $photosDirectory = $this->temporaryDirectory . '/photos';
        $quarantineDirectory = $this->temporaryDirectory . '/quarantine';
        mkdir($photosDirectory, 0775, true);
        $filename = 'photo-quarantaine.jpg';
        file_put_contents($photosDirectory . '/' . $filename, 'photo temporaire');
        $uploader = new FileUploader(
            $photosDirectory,
            new AsciiSlugger(),
            $quarantineDirectory,
        );

        self::assertTrue($uploader->quarantine($filename));
        self::assertFileDoesNotExist($photosDirectory . '/' . $filename);
        self::assertSame([$filename], $uploader->quarantinedFilenames());

        $uploader->restoreQuarantined($filename);
        self::assertFileExists($photosDirectory . '/' . $filename);
        self::assertSame([], $uploader->quarantinedFilenames());

        // La deuxième mise en quarantaine simule la validation d’une suppression SQL.
        self::assertTrue($uploader->quarantine($filename));
        $uploader->removeQuarantined($filename);

        self::assertFileDoesNotExist($photosDirectory . '/' . $filename);
        self::assertSame([], $uploader->quarantinedFilenames());
    }

    private function createSourceFile(string $name, string $content): string
    {
        if (!is_dir($this->temporaryDirectory)) {
            mkdir($this->temporaryDirectory, 0775, true);
        }

        $path = $this->temporaryDirectory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}


