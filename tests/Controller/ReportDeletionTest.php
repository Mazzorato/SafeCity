<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Photo;
use App\Entity\Profile;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\ReportStatusEnum;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie la suppression administrative du dossier et de son fichier local.
 */
final class ReportDeletionTest extends WebTestCase
{
    public function testAdminDeletionRemovesDatabaseRecordAndLocalPhoto(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test fonctionnel.');
        }

        $client = static::createClient();
        $client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        $filename = 'report-delete-test-' . bin2hex(random_bytes(8)) . '.jpg';
        $photoPath = \dirname(__DIR__, 2) . '/public/uploads/photos/' . $filename;

        try {
            $city = $entityManager->getRepository(City::class)->findOneBy([]);
            $category = $entityManager->getRepository(ReportCategory::class)->findOneBy([]);
            self::assertInstanceOf(City::class, $city);
            self::assertInstanceOf(ReportCategory::class, $category);

            $profile = (new Profile())
                ->setEmergencyNotifications(true)
                ->setWeatherNotifications(true)
                ->setTransportNotifications(true)
                ->setEventNotifications(true)
                ->setCameraAccess(true)
                ->setLocationAccess(true)
                ->setLanguage('fr');
            $admin = (new User())
                ->setFirstName('Administration')
                ->setLastName('Test')
                ->setEmail('admin-delete-' . bin2hex(random_bytes(6)) . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_ADMIN)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);
            $report = (new Report())
                ->setDescription('Signalement temporaire à supprimer.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setLatitude('43.6045000')
                ->setLongitude('1.4440000')
                ->setAddress('Adresse de suppression')
                ->setCreatedAt(new \DateTime())
                ->setReporter($admin)
                ->setCategory($category)
                ->setCity($city);
            $photo = (new Photo())
                ->setUrl('/uploads/photos/' . $filename)
                ->setUploadedAt(new \DateTime())
                ->setUploader($admin);
            $report->addPhoto($photo);

            foreach ([$profile, $admin, $report, $photo] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            file_put_contents($photoPath, 'photo temporaire du test de suppression');
            self::assertFileExists($photoPath);

            $reportId = (int) $report->getId();
            $client->loginUser($admin);
            $crawler = $client->request('GET', '/report/' . $reportId . '/edit');

            self::assertResponseIsSuccessful();
            self::assertStringContainsString(
                '/assets/controllers/confirm_controller',
                (string) $client->getResponse()->getContent(),
            );
            $deleteFormNode = $crawler->filter('form[data-controller~="confirm"]');
            self::assertCount(1, $deleteFormNode);
            self::assertSame('submit->confirm#submit', $deleteFormNode->attr('data-action'));
            self::assertNull($deleteFormNode->attr('onsubmit'));

            $client->submit($deleteFormNode->form());

            self::assertResponseRedirects('/report');
            self::assertFileDoesNotExist($photoPath);

            // La lecture est refaite depuis PostgreSQL après avoir vidé l’identité Doctrine.
            $entityManager->clear();
            self::assertNull($entityManager->getRepository(Report::class)->find($reportId));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            // Le test ne peut supprimer que le fichier aléatoire qu’il vient de créer.
            if (is_file($photoPath)) {
                unlink($photoPath);
            }
        }
    }
}

