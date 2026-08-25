<?php

namespace App\Tests\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Localization\SupportedLocale;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Vérifie que le choix de langue est complet et réellement appliqué à l’interface.
 */
final class LocalePreferenceTest extends WebTestCase
{
    public function testRegistrationOffersEverySupportedLanguageWithFrenchByDefault(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test fonctionnel.');
        }

        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertCount(
            count(SupportedLocale::ALL),
            $crawler->filter('select[name="registration_form[interfaceLanguage]"] option')
        );
        self::assertSelectorExists(
            'select[name="registration_form[interfaceLanguage]"] option[value="fr"][selected]'
        );
    }

    public function testAuthenticatedInterfaceUsesTheProfileLanguage(): void
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
            // Le scénario réutilise un profil local et annule les deux choix de
            // langue à la fin afin de ne conserver aucune donnée de test.
            $user = $entityManager->getRepository(User::class)
                ->createQueryBuilder('user')
                ->innerJoin('user.profile', 'profile')
                ->addSelect('profile')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            self::assertInstanceOf(User::class, $user);
            self::assertInstanceOf(Profile::class, $user->getProfile());
            $profileId = $user->getProfile()->getId();
            self::assertNotNull($profileId);

            $client->loginUser($user);

            $user->getProfile()->setLanguage('fr');
            $entityManager->flush();
            $crawler = $client->request('GET', '/profile');

            self::assertResponseIsSuccessful();
            $form = $crawler->selectButton('Enregistrer les préférences')->form();
            $form['profile_form[language]']->select('en');
            $client->submit($form);

            self::assertResponseRedirects('/profile');
            $crawler = $client->followRedirect();
            self::assertSelectorExists('html[lang="en"]');
            self::assertSelectorTextContains('h1', 'Profile');
            self::assertResponseHeaderSame('Content-Language', 'en');
            $savedProfile = static::getContainer()
                ->get(EntityManagerInterface::class)
                ->getRepository(Profile::class)
                ->find($profileId);
            self::assertInstanceOf(Profile::class, $savedProfile);
            self::assertSame('en', $savedProfile->getLanguage());

            // Le second changement part cette fois d’une interface anglaise :
            // il vérifie que le parcours reste utilisable après bascule.
            $form = $crawler->selectButton('Save preferences')->form();
            $form['profile_form[language]']->select('ja');
            $client->submit($form);

            self::assertResponseRedirects('/profile');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('html[lang="ja"]');
            self::assertSelectorTextContains('h1', 'プロフィール');
            self::assertResponseHeaderSame('Content-Language', 'ja');
            $savedProfile = static::getContainer()
                ->get(EntityManagerInterface::class)
                ->getRepository(Profile::class)
                ->find($profileId);
            self::assertInstanceOf(Profile::class, $savedProfile);
            self::assertSame('ja', $savedProfile->getLanguage());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    public function testEveryCatalogueTranslatesARepresentativeInterfaceLabel(): void
    {
        static::bootKernel();

        $translator = static::getContainer()->get(TranslatorInterface::class);
        foreach (SupportedLocale::ALL as $locale) {
            // Une clé représentative détecte un catalogue absent ou une langue
            // qui retomberait silencieusement sur le français.
            $translated = $translator->trans('profile.title', locale: $locale);

            self::assertNotSame('profile.title', $translated, sprintf(
                'Le catalogue %s doit contenir les libellés de l’interface.',
                $locale,
            ));
        }
    }
}