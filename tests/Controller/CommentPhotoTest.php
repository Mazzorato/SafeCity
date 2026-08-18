<?php

namespace App\Tests\Controller;

use App\Entity\Comment;
use App\Entity\City;
use App\Entity\Photo;
use App\Entity\Profile;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\ReportStatusHistory;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\ReportStatusEnum;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Vérifie le parcours complet d’ajout et d’affichage d’une photo de commentaire.
 */
final class CommentPhotoTest extends WebTestCase
{
    public function testAuthenticatedUserCanAddPhotoToComment(): void
    {
        if (!extension_loaded('pdo_pgsql') || !extension_loaded('fileinfo')) {
            self::markTestSkipped('Les extensions PHP pdo_pgsql et fileinfo sont nécessaires au test fonctionnel.');
        }

        $client = static::createClient();
        $client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        $temporaryDirectory = \dirname(__DIR__, 2) . '/var/tests/comment-photo-' . bin2hex(random_bytes(6));
        $sourcePhotoPath = $temporaryDirectory . '/commentaire.png';
        $uploadedPhotoPath = null;

        try {
            mkdir($temporaryDirectory, 0775, true);
            file_put_contents(
                $sourcePhotoPath,
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                    true
                )
            );

            $city = (new City())
                ->setName('Ville commentaire ' . bin2hex(random_bytes(4)))
                ->setPostalCode('99997')
                ->setDepartment('Test')
                ->setAvailable(true)
                ->setLatitude('43.6046520')
                ->setLongitude('1.4442090');
            $profile = (new Profile())
                ->setEmergencyNotifications(true)
                ->setTransportNotifications(true)
                ->setEventNotifications(true)
                ->setCameraAccess(true)
                ->setLocationAccess(true)
                ->setLanguage('fr');
            $user = (new User())
                ->setFirstName('Commentaire')
                ->setLastName('Photo')
                ->setEmail('comment-photo-' . bin2hex(random_bytes(6)) . '@example.test')
                ->setPassword('mot-de-passe-hache-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);
            $category = (new ReportCategory())
                ->setName('Categorie photo ' . bin2hex(random_bytes(4)))
                ->setDescription('Categorie temporaire')
                ->setIcon('photo');

            foreach ([$city, $profile, $user, $category] as $entity) {
                $entityManager->persist($entity);
            }

            // La base de test ne contient aucun signalement permanent. Celui-ci
            // existe seulement dans la transaction annulée à la fin du test.
            $reportedAt = new \DateTime('-1 minute');
            $report = (new Report())
                ->setDescription('Signalement temporaire pour tester une photo de commentaire.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setLatitude('43.6046520')
                ->setLongitude('1.4442090')
                ->setAddress('Toulouse')
                ->setCreatedAt($reportedAt)
                ->setReporter($user)
                ->setCategory($category)
                ->setCity($user->getCity());
            $initialHistory = (new ReportStatusHistory())
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setChangedAt(\DateTimeImmutable::createFromMutable($reportedAt))
                ->setChangedBy($user);
            $report->addStatusHistory($initialHistory);

            $entityManager->persist($report);
            $entityManager->persist($initialHistory);
            $entityManager->flush();

            $content = 'Commentaire temporaire avec photo ' . bin2hex(random_bytes(6));
            $client->loginUser($user);
            $crawler = $client->request('GET', '/report/' . $report->getId());

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form[name="comment"][data-controller~="photo-preview"]');
            self::assertSelectorExists('input[name="comment[photo]"][accept*="image/jpeg"]');

            $form = $crawler->filter('form[name="comment"]')->form();
            $csrfToken = bin2hex(random_bytes(18));
            $client->getCookieJar()->set(new Cookie('submit_' . $csrfToken, 'submit'));

            $form['comment[_token]'] = $csrfToken;
            $form['comment[content]'] = $content;
            $form['comment[photo]']->upload($sourcePhotoPath);
            $client->submit($form);

            self::assertResponseRedirects('/report/' . $report->getId());

            $comment = $entityManager->getRepository(Comment::class)->findOneBy([
                'content' => $content,
            ]);
            self::assertInstanceOf(Comment::class, $comment);

            $photo = $entityManager->getRepository(Photo::class)->findOneBy([
                'comment' => $comment,
            ]);
            self::assertInstanceOf(Photo::class, $photo);
            self::assertSame($report->getId(), $photo->getReport()?->getId());
            self::assertSame($user->getId(), $photo->getUploader()?->getId());

            $uploadedPhotoPath = \dirname(__DIR__, 2) . '/public' . $photo->getUrl();
            self::assertFileExists($uploadedPhotoPath);

            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#comments', $content);
            self::assertSelectorTextContains('#comments', '1 photo ajoutée');
            self::assertSelectorExists(sprintf(
                '#comments img[src="%s"][alt="Photo ajoutée au commentaire"]',
                $photo->getUrl()
            ));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            // Seuls les fichiers temporaires créés par ce test sont nettoyés.
            foreach ([$uploadedPhotoPath, $sourcePhotoPath] as $path) {
                if ($path !== null && is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($temporaryDirectory)) {
                rmdir($temporaryDirectory);
            }
        }
    }
}
