<?php

namespace App\Tests\Service;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\EventFavorite;
use App\Entity\Notification;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\EventCategoryEnum;
use App\Enum\NotificationTypeEnum;
use App\Enum\RoleEnum;
use App\Service\EventReminderSender;
use App\Service\UserNotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Vérifie l’échéance, l’idempotence et les préférences des rappels planifiés.
 */
final class EventReminderSenderTest extends KernelTestCase
{
    #[Test]
    public function itSendsOneReminderAndHonorsDisabledPreferences(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test d’intégration.');
        }

        static::bootKernel();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $city = (new City())
                ->setName('Ville rappel ' . bin2hex(random_bytes(4)))
                ->setPostalCode('99996')
                ->setDepartment('Test')
                ->setAvailable(true)
                ->setLatitude('43.6045000')
                ->setLongitude('1.4440000');

            $now = new \DateTimeImmutable('2026-07-29 12:00:00');
            $profile = $this->createProfile(true);
            $user = $this->createUser($city, $profile);
            $event = $this->createEvent(
                $city,
                \DateTime::createFromImmutable($now->modify('+23 hours')),
            );
            $favorite = $this->createFavorite($user, $event);

            foreach ([$city, $profile, $user, $event, $favorite] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            // Le hub simulé vérifie Mercure sans effectuer aucun accès réseau.
            $hub = $this->createMock(HubInterface::class);
            $hub
                ->expects(self::once())
                ->method('publish')
                ->willReturn('event-reminder-test');
            $publisher = new UserNotificationPublisher(
                $hub,
                $this->createStub(LoggerInterface::class),
            );
            $translator = $this->createStub(TranslatorInterface::class);
            $translator->method('trans')->willReturnCallback(
                static function (string $id, array $parameters = []): string {
                    return $id === 'notification.event_reminder_title'
                        ? 'Rappel : ' . ($parameters['%event%'] ?? '')
                        : $id;
                }
            );
            $sender = new EventReminderSender(
                static::getContainer()->get(\App\Repository\EventFavoriteRepository::class),
                $entityManager,
                $publisher,
                $translator,
            );

            self::assertSame(1, $sender->sendDue($now));
            self::assertSame($now, $favorite->getRemindedAt());

            $notifications = $entityManager->getRepository(Notification::class)->findBy([
                'recipient' => $user,
            ]);
            self::assertCount(1, $notifications);
            self::assertSame(NotificationTypeEnum::EVENT, $notifications[0]->getType());
            self::assertStringContainsString((string) $event->getTitle(), (string) $notifications[0]->getTitle());

            // Le second passage à la même heure ne doit produire aucun doublon.
            self::assertSame(0, $sender->sendDue($now));
            self::assertCount(
                1,
                $entityManager->getRepository(Notification::class)->findBy(['recipient' => $user])
            );

            // Un second favori éligible reste silencieux si la préférence
            // globale de l’utilisateur est désactivée.
            $profile->setEventNotifications(false);
            $secondEvent = $this->createEvent(
                $city,
                \DateTime::createFromImmutable($now->modify('+22 hours')),
            );
            $secondFavorite = $this->createFavorite($user, $secondEvent);
            $entityManager->persist($secondEvent);
            $entityManager->persist($secondFavorite);
            $entityManager->flush();

            self::assertSame(0, $sender->sendDue($now));
            self::assertNull($secondFavorite->getRemindedAt());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createProfile(bool $eventNotifications): Profile
    {
        return (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications($eventNotifications)
            ->setCameraAccess(true)
            ->setLocationAccess(true)
            ->setLanguage('fr');
    }

    private function createUser(City $city, Profile $profile): User
    {
        return (new User())
            ->setFirstName('Utilisateur')
            ->setLastName('Rappel')
            ->setEmail('reminder-' . bin2hex(random_bytes(6)) . '@example.test')
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(new \DateTime())
            ->setRole(RoleEnum::ROLE_USER)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setProfile($profile)
            ->setIsVerified(true);
    }

    private function createEvent(City $city, \DateTime $startedAt): Event
    {
        return (new Event())
            ->setTitle('Rappel temporaire ' . bin2hex(random_bytes(4)))
            ->setDescription('Événement temporaire du test de rappel.')
            ->setLocation('Toulouse')
            ->setStartedAt($startedAt)
            ->setEndedAt((clone $startedAt)->modify('+2 hours'))
            ->setIsFree(true)
            ->setCategory(EventCategoryEnum::CULTURE)
            ->setCity($city);
    }

    private function createFavorite(User $user, Event $event): EventFavorite
    {
        return (new EventFavorite())
            ->setReminderActive(true)
            ->setAddedAt(new \DateTime())
            ->setEventUser($user)
            ->setEvent($event);
    }
}
