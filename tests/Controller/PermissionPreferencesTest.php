<?php

namespace App\Tests\Controller;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que les préférences pilotent réellement les actions GPS et caméra.
 */
final class PermissionPreferencesTest extends WebTestCase
{
    public function testEnabledPreferencesExposeLocationAndCameraActions(): void
    {
        $this->assertPreferenceInterface(true);
    }

    public function testDisabledPreferencesBlockLocationButKeepGallery(): void
    {
        $this->assertPreferenceInterface(false);
    }

    private function assertPreferenceInterface(bool $allowed): void
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
            // Réutilise un profil local existant et annule chaque préférence
            // après le scénario grâce à la transaction du test.
            $user = $entityManager->getRepository(User::class)
                ->createQueryBuilder('user')
                ->innerJoin('user.profile', 'profile')
                ->addSelect('profile')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            self::assertInstanceOf(User::class, $user);
            self::assertInstanceOf(Profile::class, $user->getProfile());
            self::assertNotNull($user->getCity());

            $user->getProfile()
                ->setCameraAccess($allowed)
                ->setLocationAccess($allowed);
            $entityManager->flush();

            $client->loginUser($user);
            $client->request('GET', '/report/new');

            self::assertResponseIsSuccessful();
            self::assertSelectorExists(sprintf(
                '[data-controller~="report-location"][data-report-location-allowed-value="%s"]',
                $allowed ? 'true' : 'false'
            ));
            self::assertSelectorExists('input[name="report[photo1]"][accept*="image/jpeg"]');

            if ($allowed) {
                self::assertSelectorExists('[data-report-location-target="button"]:not([disabled])');
                self::assertSelectorExists('[data-camera-access="true"] [data-action="photo-preview#capture"]');
            } else {
                self::assertSelectorExists('[data-report-location-target="button"][disabled]');
                self::assertSelectorNotExists('[data-action="photo-preview#capture"]');
                self::assertSelectorTextContains('main', 'La galerie reste disponible.');
            }

            $client->request('GET', '/city');

            self::assertResponseIsSuccessful();
            self::assertSelectorExists(sprintf(
                '[data-controller~="city-location"][data-city-location-allowed-value="%s"]',
                $allowed ? 'true' : 'false'
            ));

            if ($allowed) {
                self::assertSelectorExists('[data-city-location-target="button"]:not([disabled])');
            } else {
                self::assertSelectorExists('[data-city-location-target="button"][disabled]');
                self::assertSelectorTextContains('main', 'Géolocalisation désactivée dans vos préférences.');
                self::assertSelectorExists('a[href="/profile"]');
            }
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}
