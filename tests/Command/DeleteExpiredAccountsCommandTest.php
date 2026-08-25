<?php

namespace App\Tests\Command;

use App\Command\DeleteExpiredAccountsCommand;
use App\Entity\City;
use App\Entity\Comment;
use App\Entity\ModerationCase;
use App\Entity\Notification;
use App\Entity\Photo;
use App\Entity\Profile;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use App\Enum\NotificationTypeEnum;
use App\Enum\ReportStatusEnum;
use App\Enum\RoleEnum;
use App\Repository\ModerationCaseRepository;
use App\Service\FileUploader;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Vérifie la suppression à trente jours sans toucher aux comptes locaux réels.
 */
final class DeleteExpiredAccountsCommandTest extends KernelTestCase
{
    public function testOnlyExpiredDisabledAccountAndPersonalDataAreDeleted(): void
    {
        if (!extension_loaded('pdo_pgsql') && !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Une extension PDO PostgreSQL ou SQLite est nécessaire au test d’intégration.');
        }

        static::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        // En l'absence de Docker, ce même test peut construire un schéma
        // éphémère SQLite en mémoire sans toucher à la base PostgreSQL locale.
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            (new SchemaTool($entityManager))->createSchema($metadata);
        }

        $connection->beginTransaction();
        $temporaryDirectory = \dirname(__DIR__, 2) . '/var/tests/account-deletion-'
            . bin2hex(random_bytes(6));
        $photosDirectory = $temporaryDirectory . '/photos';
        $quarantineDirectory = $temporaryDirectory . '/quarantine';
        mkdir($photosDirectory, 0775, true);

        try {
            $city = $this->createCity();
            $category = (new ReportCategory())
                ->setName('Suppression temporaire ' . bin2hex(random_bytes(4)))
                ->setDescription('Catégorie créée uniquement pour le test.')
                ->setIcon('test');
            $expiredUser = $this->createUser($city, 'expired', new \DateTimeImmutable('-31 days'));
            $recentUser = $this->createUser($city, 'recent', new \DateTimeImmutable('-29 days'));
            $report = (new Report())
                ->setDescription('Signalement temporaire à supprimer.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setCreatedAt(new \DateTime())
                ->setReporter($expiredUser)
                ->setCategory($category)
                ->setCity($city);
            $comment = (new Comment())
                ->setContent('Commentaire temporaire à supprimer.')
                ->setCreatedAt(new \DateTime())
                ->setAuthor($expiredUser)
                ->setReport($report);
            $filename = 'photo-compte-expire.jpg';
            file_put_contents($photosDirectory . '/' . $filename, 'photo temporaire');
            $photo = (new Photo())
                ->setUrl('/uploads/photos/' . $filename)
                ->setUploadedAt(new \DateTime())
                ->setUploader($expiredUser)
                ->setReport($report);

            // Cette photo encore référencée simule une exécution interrompue :
            // la commande devra la restaurer avant de traiter le compte expiré.
            $recentReport = (new Report())
                ->setDescription('Signalement récent à conserver.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setCreatedAt(new \DateTime())
                ->setReporter($recentUser)
                ->setCategory($category)
                ->setCity($city);
            $recentFilename = 'photo-compte-recent.jpg';
            file_put_contents($photosDirectory . '/' . $recentFilename, 'photo récente');
            $recentPhoto = (new Photo())
                ->setUrl('/uploads/photos/' . $recentFilename)
                ->setUploadedAt(new \DateTime())
                ->setUploader($recentUser)
                ->setReport($recentReport);
            $expiredNotification = $this->createNotification($expiredUser, 'Notification expirée');
            $recentNotification = $this->createNotification($recentUser, 'Notification conservée');
            $resetRequest = new ResetPasswordRequest(
                $expiredUser,
                new \DateTimeImmutable('+1 hour'),
                'temporary-selector',
                hash('sha256', 'temporary-token'),
            );

            foreach ([
                $city,
                $category,
                $expiredUser->getProfile(),
                $recentUser->getProfile(),
                $expiredUser,
                $recentUser,
                $report,
                $comment,
                $photo,
                $recentReport,
                $recentPhoto,
                $expiredNotification,
                $recentNotification,
                $resetRequest,
            ] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $commentModeration = $this->createModerationCase(
                ModerationTargetEnum::COMMENT,
                (int) $comment->getId(),
                $expiredUser,
            );
            $photoModeration = $this->createModerationCase(
                ModerationTargetEnum::PHOTO,
                (int) $photo->getId(),
                $expiredUser,
            );
            $preservedModeration = $this->createModerationCase(
                ModerationTargetEnum::COMMENT,
                999999,
                $recentUser,
            );
            foreach ([$commentModeration, $photoModeration, $preservedModeration] as $moderationCase) {
                $entityManager->persist($moderationCase);
            }
            $entityManager->flush();

            $expiredUserId = $expiredUser->getId();
            $recentUserId = $recentUser->getId();
            $expiredNotificationId = $expiredNotification->getId();
            $recentNotificationId = $recentNotification->getId();
            $resetRequestId = $resetRequest->getId();
            $reportId = $report->getId();
            $commentId = $comment->getId();
            $photoId = $photo->getId();
            $recentReportId = $recentReport->getId();
            $recentPhotoId = $recentPhoto->getId();
            $commentModerationId = $commentModeration->getId();
            $photoModerationId = $photoModeration->getId();
            $preservedModerationId = $preservedModeration->getId();

            $fileUploader = new FileUploader(
                $photosDirectory,
                new AsciiSlugger(),
                $quarantineDirectory,
            );
            self::assertTrue($fileUploader->quarantine($recentFilename));
            $orphanFilename = 'photo-orpheline.jpg';
            file_put_contents($photosDirectory . '/' . $orphanFilename, 'photo orpheline');
            self::assertTrue($fileUploader->quarantine($orphanFilename));

            $command = new DeleteExpiredAccountsCommand(
                $entityManager,
                $fileUploader,
                static::getContainer()->get(ModerationCaseRepository::class),
            );
            $tester = new CommandTester($command);

            self::assertSame(Command::SUCCESS, $tester->execute([]));
            self::assertStringContainsString('1 compte supprimé définitivement.', $tester->getDisplay());
            self::assertStringContainsString('restaurée après une interruption', $tester->getDisplay());
            self::assertStringContainsString('supprimée de la quarantaine', $tester->getDisplay());
            self::assertFileDoesNotExist($photosDirectory . '/' . $filename);
            self::assertFileExists($photosDirectory . '/' . $recentFilename);
            self::assertFileDoesNotExist($photosDirectory . '/' . $orphanFilename);
            self::assertDirectoryDoesNotExist($quarantineDirectory);

            $entityManager->clear();
            self::assertNull($entityManager->find(User::class, $expiredUserId));
            self::assertInstanceOf(User::class, $entityManager->find(User::class, $recentUserId));
            self::assertNull($entityManager->find(Notification::class, $expiredNotificationId));
            self::assertInstanceOf(Notification::class, $entityManager->find(Notification::class, $recentNotificationId));
            self::assertNull($entityManager->find(ResetPasswordRequest::class, $resetRequestId));
            self::assertNull($entityManager->find(Report::class, $reportId));
            self::assertNull($entityManager->find(Comment::class, $commentId));
            self::assertNull($entityManager->find(Photo::class, $photoId));
            self::assertInstanceOf(Report::class, $entityManager->find(Report::class, $recentReportId));
            self::assertInstanceOf(Photo::class, $entityManager->find(Photo::class, $recentPhotoId));
            self::assertNull($entityManager->find(ModerationCase::class, $commentModerationId));
            self::assertNull($entityManager->find(ModerationCase::class, $photoModerationId));
            self::assertInstanceOf(
                ModerationCase::class,
                $entityManager->find(ModerationCase::class, $preservedModerationId),
            );
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            // Le seul dossier supprimé est celui créé dans le répertoire
            // temporaire pour vérifier le nettoyage des photos du compte.
            $this->removeTemporaryDirectory($temporaryDirectory);
        }
    }

    public function testDatabaseFailureRestoresQuarantinedPhotoAndKeepsAccount(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test transactionnel.');
        }

        static::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $temporaryDirectory = \dirname(__DIR__, 2) . '/var/tests/account-rollback-'
            . bin2hex(random_bytes(6));
        $photosDirectory = $temporaryDirectory . '/photos';
        $quarantineDirectory = $temporaryDirectory . '/quarantine';
        mkdir($photosDirectory, 0775, true);
        $listener = null;

        try {
            $city = $this->createCity();
            $category = (new ReportCategory())
                ->setName('Retour arrière ' . bin2hex(random_bytes(4)))
                ->setDescription('Catégorie temporaire du test transactionnel.')
                ->setIcon('rollback');
            $user = $this->createUser($city, 'rollback', new \DateTimeImmutable('-31 days'));
            $report = (new Report())
                ->setDescription('Signalement conservé après un échec simulé.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setCreatedAt(new \DateTime())
                ->setReporter($user)
                ->setCategory($category)
                ->setCity($city);
            $filename = 'photo-retour-arriere.jpg';
            file_put_contents($photosDirectory . '/' . $filename, 'photo à restaurer');
            $photo = (new Photo())
                ->setUrl('/uploads/photos/' . $filename)
                ->setUploadedAt(new \DateTime())
                ->setUploader($user)
                ->setReport($report);

            foreach ([$city, $category, $user->getProfile(), $user, $report, $photo] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $userId = (int) $user->getId();
            $reportId = (int) $report->getId();
            $photoId = (int) $photo->getId();

            // Cette écoute ne modifie pas le schéma : elle interrompt uniquement
            // le flush de la commande afin d’exercer son retour arrière.
            $listener = new class {
                public function onFlush(): void
                {
                    throw new \RuntimeException('Échec Doctrine simulé par le test.');
                }
            };
            $entityManager->getEventManager()->addEventListener([Events::onFlush], $listener);

            $command = new DeleteExpiredAccountsCommand(
                $entityManager,
                new FileUploader($photosDirectory, new AsciiSlugger(), $quarantineDirectory),
                static::getContainer()->get(ModerationCaseRepository::class),
            );
            $tester = new CommandTester($command);

            self::assertSame(Command::FAILURE, $tester->execute([]));
            self::assertStringContainsString('suppression des comptes a été annulée', $tester->getDisplay());
            self::assertFileExists($photosDirectory . '/' . $filename);
            self::assertDirectoryDoesNotExist($quarantineDirectory);

            self::assertInstanceOf(User::class, $entityManager->find(User::class, $userId));
            self::assertInstanceOf(Report::class, $entityManager->find(Report::class, $reportId));
            self::assertInstanceOf(Photo::class, $entityManager->find(Photo::class, $photoId));
        } finally {
            if ($listener !== null) {
                $entityManager->getEventManager()->removeEventListener([Events::onFlush], $listener);
            }
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->removeTemporaryDirectory($temporaryDirectory);
        }
    }

    private function createCity(): City
    {
        return (new City())
            ->setName('Ville suppression ' . bin2hex(random_bytes(4)))
            ->setPostalCode('31000')
            ->setDepartment('Haute-Garonne')
            ->setAvailable(true)
            ->setLatitude('43.6045000')
            ->setLongitude('1.4442000');
    }

    private function createUser(City $city, string $suffix, \DateTimeImmutable $deleteRequestedAt): User
    {
        $profile = (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(false)
            ->setLocationAccess(false)
            ->setLanguage('fr');

        return (new User())
            ->setFirstName('Compte')
            ->setLastName('Suppression')
            ->setEmail($suffix . '-' . bin2hex(random_bytes(6)) . '@example.test')
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(new \DateTime('-1 year'))
            ->setRole(RoleEnum::ROLE_USER)
            ->setCguAccepted(true)
            ->setAccountActive(false)
            ->setCity($city)
            ->setProfile($profile)
            ->setDeleteRequestedAt($deleteRequestedAt)
            ->setIsVerified(true);
    }

    private function createNotification(User $user, string $title): Notification
    {
        return (new Notification())
            ->setTitle($title)
            ->setMessage('Notification temporaire du test.')
            ->setType(NotificationTypeEnum::EVENT)
            ->setSentAt(new \DateTime())
            ->setIsRead(false)
            ->setRecipient($user);
    }

    private function createModerationCase(
        ModerationTargetEnum $targetType,
        int $targetId,
        User $author,
    ): ModerationCase {
        return (new ModerationCase())
            ->setTargetType($targetType)
            ->setTargetId($targetId)
            ->setReason('Dossier de modération temporaire.')
            ->setStatus(ModerationStatusEnum::FLAGGED)
            ->setReportedAt(new \DateTimeImmutable())
            ->setReporter($author)
            ->setAuthor($author);
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        // Ce parcours CHILD_FIRST ne retire que le dossier aléatoire créé par ce test.
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}