<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\LocalService;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Enum\ServiceTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le classement local et l'interface cartographique des services de santé.
 */
final class HealthServiceProximityTest extends WebTestCase
{
    public function testHealthServicesAreSortedByDistanceAndExposedToTheMap(): void
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
            $city = $entityManager->getRepository(City::class)->findOneBy([]);
            self::assertInstanceOf(City::class, $city);

            $profile = $this->createProfile(true);
            $user = $this->createUser($city, $profile);
            $nearService = $this->createHealthService(
                $city,
                'Proximité test - cabinet proche',
                '43.6046000',
                '1.4442000',
            );
            $farService = $this->createHealthService(
                $city,
                'Proximité test - cabinet éloigné',
                '43.6545000',
                '1.4442000',
            );

            // Les données du scénario restent dans une transaction annulée à
            // la fin du test et ne polluent donc jamais la base locale.
            foreach ([$profile, $user, $nearService, $farService] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $crawler = $client->request('GET', '/service/health/doctor', [
                'query' => 'Proximité test',
                'latitude' => '43.6045000',
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists(
                '[data-controller~="health-location"][data-health-location-allowed-value="true"]'
            );
            self::assertSelectorExists('[data-controller~="health-map"]');
            self::assertSelectorExists('[data-health-map-user-latitude-value]');
            self::assertSelectorCount(2, '[data-service-distance]');

            $resultNames = $crawler->filter('article h3')->each(
                static fn ($node): string => trim($node->text())
            );
            self::assertSame([
                'Proximité test - cabinet proche',
                'Proximité test - cabinet éloigné',
            ], $resultNames);

            $distances = $crawler->filter('[data-service-distance]')->each(
                static fn ($node): float => (float) $node->attr('data-service-distance')
            );
            self::assertLessThan($distances[1], $distances[0]);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testInvalidOrForbiddenCoordinatesAreIgnored(): void
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
            $city = $entityManager->getRepository(City::class)->findOneBy([]);
            self::assertInstanceOf(City::class, $city);

            $profile = $this->createProfile(false);
            $user = $this->createUser($city, $profile);
            $service = $this->createHealthService(
                $city,
                'Proximité test - préférence',
                '43.6046000',
                '1.4442000',
            );

            foreach ([$profile, $user, $service] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $client->request('GET', '/service/health/doctor', [
                'query' => 'Proximité test',
                'latitude' => '43.6045000',
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists(
                '[data-controller~="health-location"][data-health-location-allowed-value="false"]'
            );
            self::assertSelectorExists('[data-health-location-target="button"][disabled]');
            self::assertSelectorNotExists('[data-service-distance]');
            self::assertSelectorNotExists('[data-health-map-user-latitude-value]');

            // Une latitude hors limites reste également sans effet après
            // réactivation de la préférence.
            $profile->setLocationAccess(true);
            $entityManager->flush();
            $client->request('GET', '/service/health/doctor', [
                'query' => 'Proximité test',
                'latitude' => ['valeur-invalide'],
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('[data-service-distance]');
            self::assertSelectorNotExists('[data-health-map-user-latitude-value]');
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createProfile(bool $locationAllowed): Profile
    {
        return (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(true)
            ->setLocationAccess($locationAllowed)
            ->setLanguage('fr');
    }

    private function createUser(City $city, Profile $profile): User
    {
        return (new User())
            ->setFirstName('Utilisateur')
            ->setLastName('Proximité')
            ->setEmail('health-' . bin2hex(random_bytes(6)) . '@example.test')
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(new \DateTime())
            ->setRole(RoleEnum::ROLE_USER)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setProfile($profile)
            ->setIsVerified(true);
    }

    private function createHealthService(
        City $city,
        string $name,
        string $latitude,
        string $longitude,
    ): LocalService {
        return (new LocalService())
            ->setName($name)
            ->setAddress('Adresse temporaire du test')
            ->setType(ServiceTypeEnum::HEALTH)
            ->setLatitude($latitude)
            ->setLongitude($longitude)
            ->setPhone('05 00 00 00 00')
            ->setOnDuty(true)
            ->setOpeningHours('Ouvert pour le test')
            ->setCity($city);
    }
}


