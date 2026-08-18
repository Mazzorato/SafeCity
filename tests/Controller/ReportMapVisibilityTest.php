<?php

namespace App\Tests\Controller;

use App\Entity\City;
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
 * Vérifie que les alertes résolues quittent la carte sans disparaître de l’API.
 */
final class ReportMapVisibilityTest extends WebTestCase
{
    public function testMapExcludesResolvedReports(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test fonctionnel.');
        }

        $client = static::createClient();
        $client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $category = $entityManager->getRepository(ReportCategory::class)->findOneBy([]);
            self::assertInstanceOf(ReportCategory::class, $category);

            // Une ville isolée garantit que le contenu JSON ne dépend d’aucune fixture.
            $city = (new City())
                ->setName('Ville carte ' . bin2hex(random_bytes(4)))
                ->setPostalCode('99998')
                ->setDepartment('Test')
                ->setAvailable(true)
                ->setLatitude('43.6045000')
                ->setLongitude('1.4440000');
            $profile = (new Profile())
                ->setEmergencyNotifications(true)
                ->setTransportNotifications(true)
                ->setEventNotifications(true)
                ->setCameraAccess(true)
                ->setLocationAccess(true)
                ->setLanguage('fr');
            $user = (new User())
                ->setFirstName('Carte')
                ->setLastName('Test')
                ->setEmail('map-' . bin2hex(random_bytes(6)) . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);
            $activeReport = $this->createReport($city, $category, $user, ReportStatusEnum::REPORTED);
            $resolvedReport = $this->createReport($city, $category, $user, ReportStatusEnum::RESOLVED);

            foreach ([$city, $profile, $user, $activeReport, $resolvedReport] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $activeId = (int) $activeReport->getId();
            $resolvedId = (int) $resolvedReport->getId();
            $client->loginUser($user);

            $crawler = $client->request('GET', '/report/map/view');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString(
                'excludeResolved=1',
                (string) $crawler->filter('[data-incident-map-reports-url-value]')->attr(
                    'data-incident-map-reports-url-value'
                )
            );

            $client->request('GET', '/api/reports?excludeResolved=1');
            self::assertResponseIsSuccessful();
            $mapPayload = json_decode(
                (string) $client->getResponse()->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $mapIds = array_column($mapPayload['data'], 'id');

            self::assertContains($activeId, $mapIds);
            self::assertNotContains($resolvedId, $mapIds);

            // Le filtre explicite reste disponible pour l’historique et les écrans de suivi.
            $client->request('GET', '/api/reports?status=resolved');
            self::assertResponseIsSuccessful();
            $resolvedPayload = json_decode(
                (string) $client->getResponse()->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertSame([$resolvedId], array_column($resolvedPayload['data'], 'id'));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createReport(
        City $city,
        ReportCategory $category,
        User $user,
        ReportStatusEnum $status,
    ): Report {
        return (new Report())
            ->setDescription('Signalement temporaire ' . $status->value)
            ->setGravityLevel(GravityLevelEnum::LOW)
            ->setStatus($status)
            ->setLatitude('43.6045000')
            ->setLongitude('1.4440000')
            ->setAddress('Adresse de test')
            ->setCreatedAt(new \DateTime())
            ->setReporter($user)
            ->setCategory($category)
            ->setCity($city);
    }
}
