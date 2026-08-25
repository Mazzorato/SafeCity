<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Comment;
use App\Entity\ModerationCase;
use App\Entity\Notification;
use App\Entity\Profile;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use App\Enum\NotificationTypeEnum;
use App\Enum\ReportStatusEnum;
use App\Enum\RoleEnum;
use App\Service\UserNotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

/**
 * Vérifie qu'un masquage administratif avertit réellement l'auteur du contenu.
 */
final class ModerationDecisionEmailTest extends WebTestCase
{
    public function testHideDecisionCreatesNotificationAndQueuesWarningEmail(): void
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
                ->setName('Ville modération ' . $suffix)
                ->setPostalCode('31000')
                ->setDepartment('Haute-Garonne')
                ->setAvailable(true);
            $author = $this->createUser($city, $suffix, RoleEnum::ROLE_USER, 'author');
            $admin = $this->createUser($city, $suffix, RoleEnum::ROLE_ADMIN, 'admin');
            $category = (new ReportCategory())
                ->setName('Catégorie modération ' . $suffix)
                ->setDescription('Catégorie temporaire du test de modération.')
                ->setIcon('autre');
            $report = (new Report())
                ->setDescription('Signalement temporaire du test de modération.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setLatitude('43.6046520')
                ->setLongitude('1.4442090')
                ->setAddress('Toulouse')
                ->setCreatedAt(new \DateTime())
                ->setReporter($author)
                ->setCategory($category)
                ->setCity($city);
            $comment = (new Comment())
                ->setContent('Commentaire à masquer ' . $suffix)
                ->setCreatedAt(new \DateTime())
                ->setAuthor($author);
            $report->addComment($comment);

            foreach ([$city, $author->getProfile(), $author, $admin->getProfile(), $admin, $category, $report, $comment] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $case = (new ModerationCase())
                ->setTargetType(ModerationTargetEnum::COMMENT)
                ->setTargetId((int) $comment->getId())
                ->setReason('inappropriate')
                ->setStatus(ModerationStatusEnum::FLAGGED)
                ->setReportedAt(new \DateTimeImmutable())
                ->setReporter($admin)
                ->setAuthor($author);
            $entityManager->persist($case);
            $entityManager->flush();

            // Un hub mémoire évite tout appel réseau tout en conservant le vrai publisher.
            $hub = new class implements HubInterface {
                public function getPublicUrl(): string
                {
                    return 'https://mercure.example.test/.well-known/mercure';
                }

                public function getFactory(): ?TokenFactoryInterface
                {
                    return null;
                }

                public function publish(Update $update): string
                {
                    return 'test-update-id';
                }
            };
            static::getContainer()->set(
                UserNotificationPublisher::class,
                new UserNotificationPublisher($hub, new NullLogger()),
            );

            $client->loginUser($admin);
            $crawler = $client->request('GET', '/admin/moderation');

            self::assertResponseIsSuccessful();
            $form = $crawler->filter(sprintf(
                'form[action="/admin/moderation/%d/hide"]',
                $case->getId(),
            ))->form();
            $client->submit($form);

            self::assertResponseRedirects('/admin/moderation');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $moderatedCase = $currentEntityManager->getRepository(ModerationCase::class)->find($case->getId());
            self::assertInstanceOf(ModerationCase::class, $moderatedCase);
            self::assertSame(ModerationStatusEnum::HIDDEN, $moderatedCase->getStatus());
            self::assertSame($admin->getId(), $moderatedCase->getModerator()?->getId());
            self::assertNotNull($moderatedCase->getModeratedAt());

            $notification = $currentEntityManager->getRepository(Notification::class)->findOneBy([
                'recipient' => $author->getId(),
                'type' => NotificationTypeEnum::MODERATION,
            ]);
            self::assertInstanceOf(Notification::class, $notification);
            self::assertFalse($notification->isRead());
            self::assertQueuedEmailCount(1);
            $warningEmail = self::getMailerMessage();
            self::assertNotNull($warningEmail);
            self::assertEmailAddressContains($warningEmail, 'To', (string) $author->getEmail());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function createUser(City $city, string $suffix, RoleEnum $role, string $prefix): User
    {
        $profile = (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(false)
            ->setLocationAccess(false)
            ->setLanguage('fr');

        return (new User())
            ->setFirstName(ucfirst($prefix))
            ->setLastName('Modération')
            ->setEmail($prefix . '-moderation-' . $suffix . '@example.test')
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(new \DateTime())
            ->setRole($role)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setProfile($profile)
            ->setIsVerified(true);
    }
}