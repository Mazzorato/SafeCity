<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que la recherche de ville respecte la casse des caractères accentués.
 */
final class CitySearchTest extends WebTestCase
{
    public function testSearchIsCaseInsensitiveForAccentedCityName(): void
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
            $user = $entityManager->getRepository(User::class)->findOneBy([]);
            self::assertInstanceOf(User::class, $user);

            // Le nom temporaire contient précisément le caractère qui n’était
            // pas normalisé par strtolower() dans l’ancien contrôleur.
            $cityName = 'Évry test ' . bin2hex(random_bytes(4));
            $city = (new City())
                ->setName($cityName)
                ->setPostalCode('91000')
                ->setDepartment('Essonne')
                ->setAvailable(true);
            $entityManager->persist($city);
            $entityManager->flush();

            $client->loginUser($user);
            $client->request('GET', '/city', [
                'query' => mb_strtoupper($cityName),
            ]);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', $cityName);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}


