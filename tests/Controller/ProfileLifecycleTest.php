<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie la modification des informations, des préférences et la désactivation du compte.
 */
final class ProfileLifecycleTest extends WebTestCase
{
    public function testUserCanUpdateProfileAndDeactivateAccount(): void
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
                ->setName('Ville profil ' . $suffix)
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
                ->setFirstName('Avant')
                ->setLastName('Modification')
                ->setEmail('profile-' . $suffix . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);

            foreach ([$city, $profile, $user] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $client->loginUser($user);

            // Les préférences sont envoyées séparément comme dans la page réelle.
            $crawler = $client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            $preferencesForm = $crawler->filter('form[name="profile_form"]')->form();
            $preferencesForm['profile_form[emergencyNotifications]']->untick();
            $preferencesForm['profile_form[cameraAccess]']->tick();
            $preferencesForm['profile_form[locationAccess]']->tick();
            $preferencesForm['profile_form[language]']->select('de');
            $client->submit($preferencesForm);

            self::assertResponseRedirects('/profile');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $savedProfile = $currentEntityManager->getRepository(Profile::class)->find($profile->getId());
            self::assertInstanceOf(Profile::class, $savedProfile);
            self::assertFalse($savedProfile->isEmergencyNotifications());
            self::assertTrue($savedProfile->isCameraAccess());
            self::assertTrue($savedProfile->isLocationAccess());
            self::assertSame('de', $savedProfile->getLanguage());

            // Le second formulaire contrôle l'identité et la ville du compte.
            $crawler = $client->followRedirect();
            $updatedEmail = 'profile-updated-' . $suffix . '@example.test';
            $userForm = $crawler->filter('form[name="user_form"]')->form();
            $userForm['user_form[firstName]'] = 'Après';
            $userForm['user_form[lastName]'] = 'Mise à jour';
            $userForm['user_form[email]'] = $updatedEmail;
            $userForm['user_form[city]']->select((string) $city->getId());
            $client->submit($userForm);

            self::assertResponseRedirects('/profile');
            $savedUser = static::getContainer()
                ->get(EntityManagerInterface::class)
                ->getRepository(User::class)
                ->find($user->getId());
            self::assertInstanceOf(User::class, $savedUser);
            self::assertSame('Après', $savedUser->getFirstName());
            self::assertSame('Mise à jour', $savedUser->getLastName());
            self::assertSame($updatedEmail, $savedUser->getEmail());

            // Le jeton CSRF provient de l'écran de confirmation de désactivation.
            $crawler = $client->request('GET', '/profile/delete/confirm');
            self::assertResponseIsSuccessful();
            $deleteForm = $crawler->filter('form[action="/profile/delete"]')->form();
            $client->submit($deleteForm);

            self::assertResponseRedirects('/logout');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $deactivatedUser = $currentEntityManager->getRepository(User::class)->find($user->getId());
            self::assertInstanceOf(User::class, $deactivatedUser);
            self::assertFalse($deactivatedUser->isAccountActive());
            self::assertNotNull($deactivatedUser->getDeleteRequestedAt());
            self::assertSame('Compte', $deactivatedUser->getFirstName());
            self::assertSame('deleted_' . $user->getId() . '@anonymous.local', $deactivatedUser->getEmail());
            self::assertFalse($deactivatedUser->getProfile()?->isCameraAccess());
            self::assertFalse($deactivatedUser->getProfile()?->isLocationAccess());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}
