<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\CityFixtures;
use App\DataFixtures\EventFixtures;
use App\DataFixtures\LocalServiceFixtures;
use App\DataFixtures\NewsFixtures;
use App\DataFixtures\ParkingFixtures;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\LocalService;
use App\Entity\News;
use App\Entity\Parking;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que les données locales peuvent être rechargées sans doublon ni purge.
 */
final class DemoContentFixturesTest extends KernelTestCase
{
    public function testCityAndEventFixturesAreCompleteAndIdempotent(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test des fixtures.');
        }

        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->loadDemoContent($entityManager);
            $countsAfterFirstLoad = $this->contentCounts($entityManager);
            // Une seconde exécution reproduit exactement la commande ciblée.
            $this->loadDemoContent($entityManager);
            self::assertSame($countsAfterFirstLoad, $this->contentCounts($entityManager));

            $cityNames = [
                'Toulouse',
                'Blagnac',
                'Colomiers',
                'Muret',
                'Tournefeuille',
                'Cugnaux',
                'Labège',
                'Plaisance-du-Touch',
                'Saint-Orens-de-Gameville',
            ];
            foreach ($cityNames as $cityName) {
                $cities = $entityManager->getRepository(City::class)->findBy(['name' => $cityName]);

                self::assertCount(1, $cities, sprintf('La ville %s ne doit pas être dupliquée.', $cityName));
                self::assertSame('Haute-Garonne', $cities[0]->getDepartment());
                self::assertNotNull($cities[0]->getLatitude());
                self::assertNotNull($cities[0]->getLongitude());

                $minimumParkingCount = $cityName === 'Toulouse' ? 6 : 3;
                $minimumServiceCount = $cityName === 'Toulouse' ? 9 : 4;
                $minimumNewsCount = $cityName === 'Toulouse' ? 5 : 2;
                self::assertGreaterThanOrEqual(
                    $minimumParkingCount,
                    $entityManager->getRepository(Parking::class)->count(['city' => $cities[0]])
                );
                self::assertGreaterThanOrEqual(
                    $minimumServiceCount,
                    $entityManager->getRepository(LocalService::class)->count(['city' => $cities[0]])
                );
                self::assertGreaterThanOrEqual(
                    $minimumNewsCount,
                    $entityManager->getRepository(News::class)->count(['city' => $cities[0]])
                );
            }

            $eventTitles = [
                'Concert local de la Garonne',
                'Course citoyenne du Capitole',
                'Visite nocturne des Augustins',
                'Forum culturel de Blagnac',
                'Tournoi sportif de Colomiers',
                'Scène musicale de Muret',
                'Rencontre culturelle de Tournefeuille',
                'Journée sportive de Cugnaux',
                'Festival local de Labège',
                'Concert au Touch',
                'Exposition citoyenne de Saint-Orens',
            ];
            foreach ($eventTitles as $eventTitle) {
                $events = $entityManager->getRepository(Event::class)->findBy(['title' => $eventTitle]);

                self::assertCount(1, $events, sprintf('L’événement %s ne doit pas être dupliqué.', $eventTitle));
                self::assertGreaterThan(new \DateTime(), $events[0]->getStartedAt());
                self::assertNotNull($events[0]->getCity());
                self::assertNotNull($events[0]->getLatitude());
                self::assertNotNull($events[0]->getLongitude());
            }

            self::assertContains('safecity_demo_content', CityFixtures::getGroups());
            self::assertContains('safecity_demo_content', EventFixtures::getGroups());
            self::assertContains('safecity_demo_content', LocalServiceFixtures::getGroups());
            self::assertContains('safecity_demo_content', NewsFixtures::getGroups());
            self::assertContains('safecity_demo_content', ParkingFixtures::getGroups());
            self::assertContains('safecity_parking_content', CityFixtures::getGroups());
            self::assertContains('safecity_parking_content', ParkingFixtures::getGroups());

            $toulouse = $entityManager->getRepository(City::class)->findOneBy(['name' => 'Toulouse']);
            self::assertNotNull($toulouse);
            $parkings = $entityManager->getRepository(Parking::class)->findBy(['city' => $toulouse]);
            self::assertCount(6, $parkings);
            foreach ($parkings as $parking) {
                self::assertNotNull($parking->getHourlyRate());
                if ($parking->isFree()) {
                    self::assertSame('0.00', $parking->getHourlyRate());
                } else {
                    self::assertGreaterThan(0.0, (float) $parking->getHourlyRate());
                }
            }
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function loadDemoContent(EntityManagerInterface $entityManager): void
    {
        // Chaque passage possède son propre registre, comme deux commandes séparées.
        $references = new ReferenceRepository($entityManager);
        $fixtures = [
            new CityFixtures(),
            new EventFixtures(),
            new LocalServiceFixtures(),
            new NewsFixtures(),
            new ParkingFixtures(),
        ];

        foreach ($fixtures as $fixture) {
            $fixture->setReferenceRepository($references);
            $fixture->load($entityManager);
        }
    }

    /**
     * @return array{cities: int, events: int, services: int, news: int, parkings: int}
     */
    private function contentCounts(EntityManagerInterface $entityManager): array
    {
        return [
            'cities' => $entityManager->getRepository(City::class)->count([]),
            'events' => $entityManager->getRepository(Event::class)->count([]),
            'services' => $entityManager->getRepository(LocalService::class)->count([]),
            'news' => $entityManager->getRepository(News::class)->count([]),
            'parkings' => $entityManager->getRepository(Parking::class)->count([]),
        ];
    }
}