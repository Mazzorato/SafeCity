<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Profile;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Vérifie les parcours publics d'inscription, de connexion et de mot de passe oublié.
 */
final class AuthenticationFlowTest extends WebTestCase
{
    public function testRegistrationQueuesConfirmationAndSignedLinkVerifiesAccount(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // La ville et l'adresse uniques rendent le scénario indépendant des fixtures.
            $suffix = bin2hex(random_bytes(6));
            $email = 'registration-' . $suffix . '@example.test';
            $city = $this->createCity($suffix);
            $entityManager->persist($city);
            $entityManager->flush();

            $crawler = $client->request('GET', '/register');

            self::assertResponseIsSuccessful();
            $form = $crawler->filter('form[name="registration_form"]')->form();
            $form['registration_form[firstName]'] = 'Alice';
            $form['registration_form[lastName]'] = 'Inscription';
            $form['registration_form[email]'] = $email;
            $form['registration_form[plainPassword][first]'] = 'SafeCity!Test-2026';
            $form['registration_form[plainPassword][second]'] = 'SafeCity!Test-2026';
            $form['registration_form[city]']->select((string) $city->getId());
            $form['registration_form[interfaceLanguage]']->select('fr');
            $form['registration_form[agreeTerms]']->tick();
            $client->submit($form);

            self::assertResponseRedirects('/login');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $user = $currentEntityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            self::assertInstanceOf(User::class, $user);
            self::assertFalse($user->isVerified());
            self::assertTrue($user->isAccountActive());
            self::assertTrue($user->isCguAccepted());
            self::assertSame('fr', $user->getProfile()?->getLanguage());
            self::assertQueuedEmailCount(1);
            $confirmationEmail = self::getMailerMessage();
            self::assertNotNull($confirmationEmail);
            self::assertEmailAddressContains($confirmationEmail, 'To', $email);

            // Le même composant que l'application produit un lien signé sans lire l'e-mail.
            $signature = static::getContainer()
                ->get(VerifyEmailHelperInterface::class)
                ->generateSignature(
                    'app_verify_email',
                    (string) $user->getId(),
                    (string) $user->getEmail(),
                    ['id' => $user->getId()],
                );
            $client->request('GET', $signature->getSignedUrl());

            self::assertResponseRedirects('/login');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $currentEntityManager->clear();
            $verifiedUser = $currentEntityManager->getRepository(User::class)->find($user->getId());
            self::assertInstanceOf(User::class, $verifiedUser);
            self::assertTrue($verifiedUser->isVerified());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testVerifiedUserCanLogInAndLogOut(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $plainPassword = 'SafeCity!Connexion-2026';
            $user = $this->createUser($entityManager, $plainPassword);

            // Le formulaire réel fournit le jeton CSRF attendu par le pare-feu.
            $crawler = $client->request('GET', '/login');
            $form = $crawler->filter('form')->form();
            $form['_username'] = $user->getEmail();
            $form['_password'] = $plainPassword;
            $client->submit($form);

            self::assertResponseRedirects();
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSame($user->getUserIdentifier(), $client->getRequest()->getSession()->get('_security.last_username'));

            $client->request('GET', '/logout');
            self::assertResponseRedirects('/login');
            $client->followRedirect();
            self::assertResponseIsSuccessful();

            $client->request('GET', '/profile');
            self::assertResponseRedirects();
            self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testRegistrationWithoutLegalConsentIsRejected(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $suffix = bin2hex(random_bytes(6));
            $email = 'consent-' . $suffix . '@example.test';
            $city = $this->createCity($suffix);
            $entityManager->persist($city);
            $entityManager->flush();

            $crawler = $client->request('GET', '/register');
            $form = $crawler->filter('form[name="registration_form"]')->form();
            $form['registration_form[firstName]'] = 'Consentement';
            $form['registration_form[lastName]'] = 'Refusé';
            $form['registration_form[email]'] = $email;
            $form['registration_form[plainPassword][first]'] = 'SafeCity!Consentement-2026';
            $form['registration_form[plainPassword][second]'] = 'SafeCity!Consentement-2026';
            $form['registration_form[city]']->select((string) $city->getId());
            $form['registration_form[interfaceLanguage]']->select('fr');
            // La case reste volontairement décochée pour vérifier la contrainte IsTrue.
            $client->submit($form);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('form', 'Vous devez accepter les conditions d’utilisation.');
            self::assertNull(
                static::getContainer()
                    ->get(EntityManagerInterface::class)
                    ->getRepository(User::class)
                    ->findOneBy(['email' => $email]),
            );
            self::assertQueuedEmailCount(0);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testUnverifiedUserCannotLogIn(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $plainPassword = 'SafeCity!NonVerifie-2026';
            $user = $this->createUser($entityManager, $plainPassword, false);
            $crawler = $client->request('GET', '/login');
            $form = $crawler->filter('form')->form();
            $form['_username'] = $user->getEmail();
            $form['_password'] = $plainPassword;
            $client->submit($form);

            self::assertResponseRedirects('/login');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '[role="alert"]',
                'Veuillez confirmer votre adresse e-mail avant de vous connecter.',
            );

            $client->request('GET', '/home');
            self::assertResponseRedirects();
            self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testUnverifiedUserCanRequestAnotherConfirmationEmail(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $user = $this->createUser($entityManager, 'SafeCity!Renvoi-2026', false);
            $crawler = $client->request('GET', '/login');

            // Le formulaire public réel fournit le jeton CSRF et l'adresse à confirmer.
            $form = $crawler->filter('form[action="/verify/email/resend"]')->form();
            $form['email'] = $user->getEmail();
            $client->submit($form);

            self::assertResponseRedirects('/login');
            self::assertQueuedEmailCount(1);
            $confirmationEmail = self::getMailerMessage();
            self::assertNotNull($confirmationEmail);
            self::assertEmailAddressContains($confirmationEmail, 'To', (string) $user->getEmail());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testPasswordResetRequestCreatesTokenAndQueuesEmail(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $user = $this->createUser($entityManager, 'SafeCity!Ancien-2026');
            $crawler = $client->request('GET', '/reset-password');

            self::assertResponseIsSuccessful();
            $form = $crawler->filter('form[name="reset_password_request_form"]')->form();
            $form['reset_password_request_form[email]'] = $user->getEmail();
            $client->submit($form);

            self::assertResponseRedirects('/reset-password/check-email');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            self::assertInstanceOf(
                ResetPasswordRequest::class,
                $currentEntityManager->getRepository(ResetPasswordRequest::class)->findOneBy(['user' => $user->getId()]),
            );
            self::assertQueuedEmailCount(1);
            $resetEmail = self::getMailerMessage();
            self::assertNotNull($resetEmail);
            self::assertEmailAddressContains($resetEmail, 'To', (string) $user->getEmail());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testValidResetTokenChangesPasswordAndCannotBeReused(): void
    {
        $this->requirePostgreSql();

        $client = static::createClient();
        $client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $user = $this->createUser($entityManager, 'SafeCity!Ancien-2026');
            $resetToken = static::getContainer()
                ->get(ResetPasswordHelperInterface::class)
                ->generateResetToken($user);
            $newPassword = 'SafeCity!Nouveau-2026-' . bin2hex(random_bytes(4));

            // Le premier passage stocke le jeton en session puis retire sa valeur de l'URL.
            $client->request('GET', '/reset-password/reset/' . $resetToken->getToken());
            self::assertResponseRedirects('/reset-password/reset');
            $crawler = $client->followRedirect();
            self::assertResponseIsSuccessful();

            $form = $crawler->filter('form[name="change_password_form"]')->form();
            $form['change_password_form[plainPassword][first]'] = $newPassword;
            $form['change_password_form[plainPassword][second]'] = $newPassword;
            $client->submit($form);

            self::assertResponseRedirects('/login');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $updatedUser = $currentEntityManager->getRepository(User::class)->find($user->getId());
            self::assertInstanceOf(User::class, $updatedUser);
            self::assertTrue(
                static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($updatedUser, $newPassword),
            );
            self::assertNull(
                $currentEntityManager->getRepository(ResetPasswordRequest::class)->findOneBy(['user' => $user->getId()]),
            );
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createUser(
        EntityManagerInterface $entityManager,
        string $plainPassword,
        bool $verified = true,
    ): User
    {
        $suffix = bin2hex(random_bytes(6));
        $city = $this->createCity($suffix);
        $profile = (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(false)
            ->setLocationAccess(false)
            ->setLanguage('fr');
        $user = (new User())
            ->setFirstName('Utilisateur')
            ->setLastName('Authentification')
            ->setEmail('authentication-' . $suffix . '@example.test')
            ->setRegistrationDate(new \DateTime())
            ->setRole(RoleEnum::ROLE_USER)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setProfile($profile)
            ->setIsVerified($verified);
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $plainPassword),
        );

        foreach ([$city, $profile, $user] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return $user;
    }

    private function createCity(string $suffix): City
    {
        return (new City())
            ->setName('Ville authentification ' . $suffix)
            ->setPostalCode('31000')
            ->setDepartment('Haute-Garonne')
            ->setAvailable(true);
    }

    private function requirePostgreSql(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test fonctionnel.');
        }
    }
}