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
 * Vérifie les horaires et les alternatives locales des services de santé.
 */
final class HealthServiceAlternativesTest extends WebTestCase
{
    public function testUnavailableDoctorShowsTwoNearestOnDutyDoctors(): void
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
            $suffix = bin2hex(random_bytes(6));
            $city = (new City())
                ->setName('Ville alternatives ' . $suffix)
                ->setPostalCode('31000')
                ->setDepartment('Haute-Garonne')
                ->setAvailable(true);
            $profile = (new Profile())
                ->setEmergencyNotifications(true)
                ->setTransportNotifications(true)
                ->setEventNotifications(true)
                ->setCameraAccess(false)
                ->setLocationAccess(false)
                ->setLanguage('fr');
            $user = (new User())
                ->setFirstName('Utilisateur')
                ->setLastName('Alternatives')
                ->setEmail('alternatives-' . $suffix . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);
            $closedDoctor = $this->createHealthService(
                $city,
                'Alternatives test - cabinet indisponible',
                '43.6045000',
                '1.4442000',
                false,
                'Lundi au vendredi, 8h - 18h',
            );
            $nearDoctor = $this->createHealthService(
                $city,
                'Cabinet de garde proche ' . $suffix,
                '43.6050000',
                '1.4442000',
                true,
                'Tous les jours, 18h - 00h',
            );
            $farDoctor = $this->createHealthService(
                $city,
                'Cabinet de garde éloigné ' . $suffix,
                '43.6250000',
                '1.4442000',
                true,
                'Ouvert 24 h/24',
            );
            $pharmacy = $this->createHealthService(
                $city,
                'Pharmacie de garde exclue ' . $suffix,
                '43.6046000',
                '1.4442000',
                true,
                'Ouvert 24 h/24',
            );

            foreach ([$city, $profile, $user, $closedDoctor, $nearDoctor, $farDoctor, $pharmacy] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $client->loginUser($user);

            // La recherche ne retourne que le cabinet indisponible ; ses
            // alternatives restent volontairement disponibles sous sa fiche.
            $crawler = $client->request('GET', '/service/health/doctor', [
                'query' => 'Alternatives test',
            ]);

            self::assertResponseIsSuccessful();
            $card = $crawler->filter(sprintf('[data-health-service-id="%d"]', $closedDoctor->getId()));
            self::assertCount(1, $card);
            self::assertStringContainsString('Lundi au vendredi, 8h - 18h', $card->text());

            $alternatives = $card->filter('[data-alternative-service-id]');
            self::assertCount(2, $alternatives);
            self::assertSame([
                'Cabinet de garde proche ' . $suffix,
                'Cabinet de garde éloigné ' . $suffix,
            ], $alternatives->filter('[data-alternative-name]')->each(
                static fn ($node): string => trim($node->text()),
            ));
            self::assertStringNotContainsString((string) $pharmacy->getName(), $card->text());

            $distances = $alternatives->each(
                static fn ($node): float => (float) $node->attr('data-alternative-distance'),
            );
            self::assertLessThan($distances[1], $distances[0]);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createHealthService(
        City $city,
        string $name,
        string $latitude,
        string $longitude,
        bool $onDuty,
        string $openingHours,
    ): LocalService {
        return (new LocalService())
            ->setName($name)
            ->setAddress('Adresse temporaire du test')
            ->setType(ServiceTypeEnum::HEALTH)
            ->setLatitude($latitude)
            ->setLongitude($longitude)
            ->setPhone('05 00 00 00 00')
            ->setOnDuty($onDuty)
            ->setOpeningHours($openingHours)
            ->setCity($city);
    }
}


