<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\EventFavorite;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\EventCategoryEnum;
use App\Enum\RoleEnum;
use App\Repository\EventFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie le parcours utilisateur des favoris et de leur rappel individuel.
 */
final class EventFavoritesTest extends WebTestCase
{
    public function testUserCanFavoriteEventAndDisableItsReminder(): void
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

            // Toutes les données métier du scénario sont annulées avec la
            // transaction : aucune fixture permanente n’est ajoutée.
            $profile = $this->createProfile(true);
            $user = $this->createUser($city, $profile);
            $event = $this->createEvent($city, new \DateTime('+10 days'));

            foreach ([$profile, $user, $event] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $crawler = $client->request('GET', '/event');

            self::assertResponseIsSuccessful();
            $favoriteForm = $crawler->filter(sprintf(
                'form[action^="/events/%d/favorite"]',
                $event->getId()
            ))->form();
            $client->submit($favoriteForm);

            self::assertResponseRedirects();

            $favoriteRepository = static::getContainer()->get(EventFavoriteRepository::class);
            $favorite = $favoriteRepository->findOneForUserAndEvent($user, $event);

            self::assertInstanceOf(EventFavorite::class, $favorite);
            self::assertTrue($favorite->isReminderActive());
            self::assertNull($favorite->getRemindedAt());

            $crawler = $client->request('GET', '/events/favorites');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', $event->getTitle());
            self::assertSelectorTextContains('main', 'Rappel activé');

            $reminderForm = $crawler->filter(sprintf(
                'form[action="/events/favorites/%d/reminder"]',
                $favorite->getId()
            ))->form();
            $client->submit($reminderForm);

            self::assertResponseRedirects('/events/favorites');

            // La requête HTTP recharge sa propre instance Doctrine : le test
            // relit donc la valeur réellement persistée avant de l’affirmer.
            $favoriteId = (int) $favorite->getId();
            $entityManager->clear();
            $favorite = $entityManager->getRepository(EventFavorite::class)->find($favoriteId);
            self::assertInstanceOf(EventFavorite::class, $favorite);
            self::assertFalse($favorite->isReminderActive());

            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Rappel désactivé');
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testUserCannotFavoriteEventFromAnotherCity(): void
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
            $homeCity = $entityManager->getRepository(City::class)->findOneBy([]);
            self::assertInstanceOf(City::class, $homeCity);

            // Cette seconde ville rend le scénario indépendant des événements
            // permanents éventuellement fournis par les fixtures.
            $otherCity = (new City())
                ->setName('Ville événement ' . bin2hex(random_bytes(4)))
                ->setPostalCode('99999')
                ->setDepartment('Test')
                ->setAvailable(true);
            $profile = $this->createProfile(true);
            $user = $this->createUser($homeCity, $profile);
            $event = $this->createEvent($homeCity, new \DateTime('+10 days'));

            foreach ([$otherCity, $profile, $user, $event] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $client->loginUser($user);
            $crawler = $client->request('GET', '/event');
            $favoriteForm = $crawler->filter(sprintf(
                'form[action^="/events/%d/favorite"]',
                $event->getId()
            ))->form();

            // Le formulaire fournit un vrai jeton Symfony ; déplacer ensuite
            // l’événement prouve que le refus vient exclusivement de sa ville.
            $event->setCity($otherCity);
            $entityManager->flush();
            $client->submit($favoriteForm);

            self::assertResponseStatusCodeSame(403);
            self::assertNull(
                static::getContainer()
                    ->get(EventFavoriteRepository::class)
                    ->findOneForUserAndEvent($user, $event)
            );
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
            ->setLastName('Favoris')
            ->setEmail('favorite-' . bin2hex(random_bytes(6)) . '@example.test')
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
            ->setTitle('Événement temporaire ' . bin2hex(random_bytes(4)))
            ->setDescription('Événement créé uniquement dans la transaction du test.')
            ->setLocation('Place du Capitole, Toulouse')
            ->setStartedAt($startedAt)
            ->setEndedAt((clone $startedAt)->modify('+2 hours'))
            ->setIsFree(true)
            ->setCategory(EventCategoryEnum::CULTURE)
            ->setCity($city);
    }
}


