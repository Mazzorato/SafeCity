<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Parking;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Service\TisseoGtfsReader;
use App\Service\TisseoOpenDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Vérifie l'affichage du sous-ensemble Tisséo autorisé sur la page transport.
 */
final class TransportControllerTest extends WebTestCase
{
    public function testTransportPageDisplaysMetrosTramAndOnlyLineoOne(): void
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
            $profile = $this->createProfile();
            $user = $this->createUser($city, $profile);
            $parking = $this->createParking($city);
            foreach ([$city, $profile, $user, $parking] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            static::getContainer()->set(TisseoOpenDataClient::class, $this->createTisseoClient());
            $client->loginUser($user);
            $client->request('GET', '/transport');

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(4, '[data-transport-line]');
            self::assertSelectorExists('[data-transport-line="A"]');
            self::assertSelectorExists('[data-transport-line="B"]');
            self::assertSelectorExists('[data-transport-line="T1"]');
            self::assertSelectorExists('[data-transport-line="L1"]');
            self::assertSelectorNotExists('[data-transport-line="L9"]');
            self::assertSelectorTextContains('#transport-schedules', 'Horaires par arrêt');

            $client->request('GET', '/transport', ['type' => 'bus']);

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, '[data-transport-line]');
            self::assertSelectorExists('[data-transport-line="L1"]');

            // L'API mobilité doit restituer le tarif sans conversion flottante.
            $client->request('GET', '/api/mobility');
            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertCount(1, $payload['parkings']);
            self::assertSame('2.40', $payload['parkings'][0]['hourlyRate']);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createTisseoClient(): TisseoOpenDataClient
    {
        $archivePath = sprintf(
            '%s%ssafecity-tisseo-controller-%s.zip',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            bin2hex(random_bytes(6)),
        );
        $files = $this->gtfsFiles();

        try {
            $archive = new \PharData($archivePath);
            foreach ($files as $filename => $content) {
                $archive->addFromString($filename, $content);
            }
            unset($archive);
            $archiveContent = file_get_contents($archivePath);
            self::assertIsString($archiveContent);
        } finally {
            // La simulation du fournisseur ne laisse aucun fichier de test.
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }

        $realtime = json_encode([
            'header' => ['timestamp' => time()],
            'entity' => [],
        ], JSON_THROW_ON_ERROR);

        return new TisseoOpenDataClient(
            new MockHttpClient([
                new MockResponse($archiveContent, ['http_code' => 200]),
                new MockResponse($realtime, ['http_code' => 200]),
            ]),
            new ArrayAdapter(),
            new TisseoGtfsReader(),
            new NullLogger(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function gtfsFiles(): array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $departure = new \DateTimeImmutable('+30 minutes', new \DateTimeZone('Europe/Paris'));
        $seconds = ((int) $departure->format('H') * 3600)
            + ((int) $departure->format('i') * 60)
            + (int) $departure->format('s');
        if ($departure->format('Ymd') !== $today->format('Ymd')) {
            $seconds += 86400;
        }
        $gtfsTime = sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );

        return [
            'routes.txt' => "route_id,route_short_name,route_long_name,route_type\n"
                . "line:61,A,Basso Cambo - Balma-Gramont,1\n"
                . "line:69,B,Ramonville - Borderouge,1\n"
                . "line:38,T1,Palais de Justice - MEETT,0\n"
                . "line:144,L1,Sept Deniers - Fonsegrives Entiore,3\n"
                . "line:174,L9,L’Union - Saint-Orens,3\n",
            'trips.txt' => "route_id,service_id,trip_id,trip_headsign,direction_id\n"
                . "line:61,service:daily,trip:A,Balma-Gramont,0\n"
                . "line:69,service:daily,trip:B,Borderouge,0\n"
                . "line:38,service:daily,trip:T1,MEETT,0\n"
                . "line:144,service:daily,trip:L1,Fonsegrives Entiore,0\n"
                . "line:174,service:daily,trip:L9,Saint-Orens,0\n",
            'stops.txt' => "stop_id,stop_name\n"
                . "stop:A,Jean Jaurès\nstop:B,Jean Jaurès\nstop:T1,Arènes\nstop:L1,Capitole\nstop:L9,François Verdier\n",
            'stop_times.txt' => "trip_id,arrival_time,departure_time,stop_id,stop_sequence\n"
                . "trip:A,$gtfsTime,$gtfsTime,stop:A,1\n"
                . "trip:B,$gtfsTime,$gtfsTime,stop:B,1\n"
                . "trip:T1,$gtfsTime,$gtfsTime,stop:T1,1\n"
                . "trip:L1,$gtfsTime,$gtfsTime,stop:L1,1\n"
                . "trip:L9,$gtfsTime,$gtfsTime,stop:L9,1\n",
            'calendar.txt' => "service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date\n"
                . 'service:daily,1,1,1,1,1,1,1,'
                . $today->modify('-1 day')->format('Ymd')
                . ','
                . $today->modify('+1 day')->format('Ymd')
                . "\n",
            'calendar_dates.txt' => "service_id,date,exception_type\n",
        ];
    }

    private function createCity(): City
    {
        return (new City())
            ->setName('Ville Tisséo ' . bin2hex(random_bytes(4)))
            ->setPostalCode('31000')
            ->setDepartment('Haute-Garonne')
            ->setAvailable(true)
            ->setLatitude('43.6045000')
            ->setLongitude('1.4442000');
    }

    private function createProfile(): Profile
    {
        return (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(true)
            ->setLocationAccess(false)
            ->setLanguage('fr');
    }

    private function createParking(City $city): Parking
    {
        return (new Parking())
            ->setName('Parking API mobilité')
            ->setAddress('Adresse temporaire du test')
            ->setLatitude('43.6046000')
            ->setLongitude('1.4442000')
            ->setIsFree(false)
            ->setHourlyRate('2.40')
            ->setAvailableSpots(15)
            ->setTotalSpots(80)
            ->setCity($city);
    }

    private function createUser(City $city, Profile $profile): User
    {
        return (new User())
            ->setFirstName('Utilisateur')
            ->setLastName('Transport')
            ->setEmail('transport-' . bin2hex(random_bytes(6)) . '@example.test')
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(new \DateTime())
            ->setRole(RoleEnum::ROLE_USER)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setProfile($profile)
            ->setIsVerified(true);
    }
}


