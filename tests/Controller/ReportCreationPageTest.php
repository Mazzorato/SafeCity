<?php

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\ReportStatusHistory;
use App\Entity\User;
use App\Enum\ReportStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Vérifie que la page complète de création d’un signalement reste accessible et interactive.
 */
final class ReportCreationPageTest extends WebTestCase
{
    public function testAuthenticatedUserCanOpenTheCompleteReportForm(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test fonctionnel.');
        }

        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy([]);

        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getCity(), 'Le compte de test doit avoir une ville.');

        $client->loginUser($user);
        $client->request('GET', '/report/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="report"]');
        self::assertSelectorExists('[data-controller~="report-location"]');
        self::assertSelectorExists('[data-controller~="photo-preview"]');
        self::assertSelectorCount(7, 'input[name="report[category]"]');
        self::assertSelectorExists('input[name="report[latitude]"]');
        self::assertSelectorExists('input[name="report[longitude]"]');
        self::assertSelectorExists('input[name="report[photo1]"][accept*="image/jpeg"]');
        self::assertSelectorExists('input[name="report[photo2]"]');
        self::assertSelectorExists('input[name="report[photo3]"]');
        self::assertSelectorTextContains('button[type="submit"]', 'Envoyer le signalement');
    }

    public function testMobilityShortcutPreselectsRoadIncidentCategory(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test fonctionnel.');
        }

        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy([]);
        $roadCategory = $entityManager->getRepository(ReportCategory::class)->findOneBy([
            'icon' => 'route',
        ]);

        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getCity(), 'Le compte de test doit avoir une ville.');
        self::assertInstanceOf(ReportCategory::class, $roadCategory);

        $client->loginUser($user);

        // Vérifie à la fois le raccourci de Mobilité et la valeur réellement
        // sélectionnée par le formulaire Symfony à son arrivée.
        $client->request('GET', '/mobility');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/report/new?category=route"]');

        $client->request('GET', '/report/new?category=route');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'input[name="report[category]"][value="%d"]:checked',
            $roadCategory->getId()
        ));
    }

    public function testReportCanBeSubmittedWithPhotoAndLocation(): void
    {
        if (!extension_loaded('pdo_pgsql') || !extension_loaded('fileinfo')) {
            self::markTestSkipped('Les extensions PHP pdo_pgsql et fileinfo sont nécessaires au test fonctionnel.');
        }

        $client = static::createClient();
        $client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        $temporaryDirectory = \dirname(__DIR__, 2) . '/var/tests/report-form-' . bin2hex(random_bytes(6));
        $photoPath = $temporaryDirectory . '/incident.png';
        $uploadedPaths = [];

        try {
            mkdir($temporaryDirectory, 0775, true);
            file_put_contents(
                $photoPath,
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                    true
                )
            );
            $user = $entityManager->getRepository(User::class)->findOneBy([]);
            // Soumet précisément la catégorie rendue disponible par le lot
            // afin de vérifier son enregistrement au-delà de la présélection.
            $category = $entityManager->getRepository(ReportCategory::class)->findOneBy([
                'icon' => 'route',
            ]);

            self::assertInstanceOf(User::class, $user);
            self::assertNotNull($user->getCity());
            self::assertInstanceOf(ReportCategory::class, $category);

            $client->loginUser($user);
            $crawler = $client->request('GET', '/report/new');
            $form = $crawler->selectButton('Envoyer le signalement')->form();

            $csrfToken = bin2hex(random_bytes(18));
            $client->getCookieJar()->set(new Cookie('submit_' . $csrfToken, 'submit'));

            $form['report[_token]'] = $csrfToken;
            $form['report[category]']->select((string) $category->getId());
            $form['report[gravityLevel]']->select('medium');
            $form['report[description]'] = 'Test fonctionnel temporaire des photos et de la géolocalisation.';
            $form['report[address]'] = '1 place du Capitole, Toulouse';
            $form['report[latitude]'] = '43.604652';
            $form['report[longitude]'] = '1.444209';
            $form['report[photo1]']->upload($photoPath);

            $client->submit($form);

            self::assertResponseRedirects();

            $report = $entityManager->getRepository(Report::class)->findOneBy(
                ['description' => 'Test fonctionnel temporaire des photos et de la géolocalisation.']
            );

            self::assertInstanceOf(Report::class, $report);
            $photos = $entityManager->getRepository(Photo::class)->findBy(['report' => $report]);
            if (isset($photos[0])) {
                $uploadedPaths[] = \dirname(__DIR__, 2) . '/public' . $photos[0]->getUrl();
            }

            foreach ($uploadedPaths as $uploadedPath) {
                self::assertFileExists($uploadedPath);
            }

            self::assertSame('43.604652', $report->getLatitude());
            self::assertSame('1.444209', $report->getLongitude());
            self::assertSame('1 place du Capitole, Toulouse', $report->getAddress());
            self::assertSame($category->getId(), $report->getCategory()?->getId());
            self::assertCount(1, $photos);

            // Un nouveau signalement doit immédiatement posséder sa première
            // entrée de chronologie, datée comme son envoi.
            $statusHistory = $entityManager->getRepository(ReportStatusHistory::class)->findBy([
                'report' => $report,
            ]);
            self::assertCount(1, $statusHistory);
            self::assertSame(ReportStatusEnum::REPORTED, $statusHistory[0]->getStatus());
            self::assertSame($user->getId(), $statusHistory[0]->getChangedBy()?->getId());
            self::assertSame(
                $report->getCreatedAt()?->format('Y-m-d H:i:s'),
                $statusHistory[0]->getChangedAt()?->format('Y-m-d H:i:s')
            );
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            foreach ($uploadedPaths as $uploadedPath) {
                if (is_file($uploadedPath)) {
                    unlink($uploadedPath);
                }
            }

            foreach ([$photoPath] as $sourcePath) {
                if (is_file($sourcePath)) {
                    unlink($sourcePath);
                }
            }

            if (is_dir($temporaryDirectory)) {
                rmdir($temporaryDirectory);
            }
        }
    }

}


