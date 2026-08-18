<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Parking;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le classement, les filtres et les permissions de la page parking.
 */
final class ParkingProximityTest extends WebTestCase
{
    public function testAddressOriginSortsParkingsAndFeedsTheMap(): void
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
            $city = $this->createCity();
            $profile = $this->createProfile(false);
            $user = $this->createUser($city, $profile);
            $nearParking = $this->createParking(
                $city,
                'Parking test proche',
                '43.6046000',
                '1.4442000',
                false,
            );
            $farParking = $this->createParking(
                $city,
                'Parking test éloigné',
                '43.6545000',
                '1.4442000',
                true,
            );

            // La ville dédiée isole les résultats du test, puis la transaction
            // annule toutes les données temporaires.
            foreach ([$city, $profile, $user, $nearParking, $farParking] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $crawler = $client->request('GET', '/parking', [
                'address' => 'Place du test',
                'source' => 'address',
                'latitude' => '43.6045000',
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists(
                '[data-controller~="parking-location"][data-parking-location-allowed-value="false"]'
            );
            self::assertSelectorExists('[data-controller~="parking-map"]');
            self::assertSelectorExists('[data-parking-map-origin-latitude-value]');
            self::assertSelectorCount(2, '[data-parking-distance]');
            self::assertSelectorTextContains(
                '[data-parking-location-target="status"]',
                'Parkings classés autour de « Place du test ».'
            );

            $resultNames = $crawler->filter('article h3')->each(
                static fn ($node): string => trim($node->text())
            );
            self::assertSame(['Parking test proche', 'Parking test éloigné'], $resultNames);

            $distances = $crawler->filter('[data-parking-distance]')->each(
                static fn ($node): float => (float) $node->attr('data-parking-distance')
            );
            self::assertLessThan($distances[1], $distances[0]);
            self::assertStringContainsString('Payant', $crawler->filter('article')->eq(0)->text());
            self::assertStringContainsString('Gratuit', $crawler->filter('article')->eq(1)->text());
            self::assertSelectorExists('[data-parking-hourly-rate="1.80"]');
            self::assertSelectorCount(1, '[data-parking-hourly-rate]');

            $client->request('GET', '/parking', [
                'type' => 'free',
                'address' => 'Place du test',
                'source' => 'address',
                'latitude' => '43.6045000',
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'article');
            self::assertSelectorTextContains('article h3', 'Parking test éloigné');
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testDeviceCoordinatesRequireTheProfilePermissionAndValidValues(): void
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
            $city = $this->createCity();
            $profile = $this->createProfile(false);
            $user = $this->createUser($city, $profile);
            $parking = $this->createParking(
                $city,
                'Parking test permission',
                '43.6046000',
                '1.4442000',
                true,
            );

            foreach ([$city, $profile, $user, $parking] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $client->request('GET', '/parking', [
                'source' => 'device',
                'latitude' => '43.6045000',
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-parking-location-target="button"][disabled]');
            self::assertSelectorNotExists('[data-parking-distance]');
            self::assertSelectorNotExists('[data-parking-map-origin-latitude-value]');

            $profile->setLocationAccess(true);
            $entityManager->flush();
            $client->request('GET', '/parking', [
                'source' => 'device',
                'latitude' => '43.6045000',
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-parking-distance]');
            self::assertSelectorExists('[data-parking-map-origin-latitude-value]');

            $client->request('GET', '/parking', [
                'source' => 'device',
                'latitude' => ['valeur-invalide'],
                'longitude' => '1.4442000',
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('[data-parking-distance]');
            self::assertSelectorNotExists('[data-parking-map-origin-latitude-value]');
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createCity(): City
    {
        return (new City())
            ->setName('Ville parking ' . bin2hex(random_bytes(4)))
            ->setPostalCode('31000')
            ->setDepartment('Haute-Garonne')
            ->setAvailable(true)
            ->setLatitude('43.6045000')
            ->setLongitude('1.4442000');
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
            ->setLastName('Parking')
            ->setEmail('parking-' . bin2hex(random_bytes(6)) . '@example.test')
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(new \DateTime())
            ->setRole(RoleEnum::ROLE_USER)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setProfile($profile)
            ->setIsVerified(true);
    }

    private function createParking(
        City $city,
        string $name,
        string $latitude,
        string $longitude,
        bool $free,
    ): Parking {
        return (new Parking())
            ->setName($name)
            ->setAddress('Adresse temporaire du test')
            ->setLatitude($latitude)
            ->setLongitude($longitude)
            ->setIsFree($free)
            // Un parking gratuit conserve un montant nul explicite en base.
            ->setHourlyRate($free ? '0.00' : '1.80')
            ->setAvailableSpots(25)
            ->setTotalSpots(100)
            ->setCity($city);
    }
}


