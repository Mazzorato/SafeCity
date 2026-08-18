<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Notification;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\NotificationTypeEnum;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que le témoin des actualités reflète les notifications non lues.
 */
final class NewsNotificationIndicatorTest extends WebTestCase
{
    public function testUnreadIndicatorDisappearsWhenNotificationIsRead(): void
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

            // Un compte dédié évite que les notifications des fixtures influencent le témoin.
            $profile = (new Profile())
                ->setEmergencyNotifications(true)
                ->setTransportNotifications(true)
                ->setEventNotifications(true)
                ->setCameraAccess(true)
                ->setLocationAccess(true)
                ->setLanguage('fr');
            $user = (new User())
                ->setFirstName('Actualités')
                ->setLastName('Test')
                ->setEmail('news-' . bin2hex(random_bytes(6)) . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);
            $notification = (new Notification())
                ->setTitle('Notification temporaire')
                ->setMessage('Cette notification existe uniquement pendant le test.')
                ->setType(NotificationTypeEnum::EVENT)
                ->setSentAt(new \DateTime())
                ->setIsRead(false)
                ->setRecipient($user);

            foreach ([$profile, $user, $notification] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $client->request('GET', '/news');

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-notification-unread-indicator]');

            $notification->setIsRead(true);
            $entityManager->flush();
            $client->request('GET', '/news');

            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('[data-notification-unread-indicator]');
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}


