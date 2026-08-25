<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Comment;
use App\Entity\ModerationCase;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use App\Enum\ReportStatusEnum;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que le fil communautaire respecte les décisions de modération.
 */
final class CommunityModerationTest extends WebTestCase
{
    public function testHiddenCommentIsAbsentFromRecentCommunityComments(): void
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
            // Les contenus uniques évitent qu’une donnée de fixture puisse produire
            // un faux résultat positif dans les assertions de la page.
            $suffix = bin2hex(random_bytes(6));
            $visibleContent = 'Commentaire communautaire visible ' . $suffix;
            $hiddenContent = 'Commentaire communautaire masqué ' . $suffix;
            $now = new \DateTime();

            // Le scénario crée toutes ses données dans la transaction du test afin
            // de rester indépendant des fixtures et de la base de développement.
            $city = (new City())
                ->setName('Ville de test ' . $suffix)
                ->setPostalCode('31000')
                ->setDepartment('Haute-Garonne')
                ->setAvailable(true);
            $user = (new User())
                ->setFirstName('Utilisateur')
                ->setLastName('Test')
                ->setEmail('community-' . $suffix . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate($now)
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setIsVerified(true);
            $category = (new ReportCategory())
                ->setName('Catégorie de test ' . $suffix)
                ->setDescription('Catégorie temporaire du test communautaire.')
                ->setIcon('autre');
            $report = (new Report())
                ->setDescription('Signalement temporaire du test communautaire.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setLatitude('43.6046520')
                ->setLongitude('1.4442090')
                ->setAddress('Toulouse')
                ->setCreatedAt($now)
                ->setReporter($user)
                ->setCategory($category)
                ->setCity($city);
            $visibleComment = (new Comment())
                ->setContent($visibleContent)
                ->setCreatedAt($now)
                ->setAuthor($user);
            $hiddenComment = (new Comment())
                ->setContent($hiddenContent)
                ->setCreatedAt((clone $now)->modify('+1 second'))
                ->setAuthor($user);

            // Maintient les deux côtés de la relation Doctrine synchronisés dans
            // l’unité de travail utilisée pendant la requête fonctionnelle.
            $report
                ->addComment($visibleComment)
                ->addComment($hiddenComment);

            $entityManager->persist($city);
            $entityManager->persist($user);
            $entityManager->persist($category);
            $entityManager->persist($report);
            $entityManager->persist($visibleComment);
            $entityManager->persist($hiddenComment);
            $entityManager->flush();

            $moderationCase = (new ModerationCase())
                ->setTargetType(ModerationTargetEnum::COMMENT)
                ->setTargetId((int) $hiddenComment->getId())
                ->setReason('Contenu masqué par le test fonctionnel.')
                ->setStatus(ModerationStatusEnum::HIDDEN)
                ->setReportedAt(new \DateTimeImmutable())
                ->setModeratedAt(new \DateTimeImmutable())
                ->setReporter($user)
                ->setAuthor($user)
                ->setModerator($user);

            $entityManager->persist($moderationCase);
            $entityManager->flush();

            $client->loginUser($user);
            $client->request('GET', '/community');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', $visibleContent);
            self::assertSelectorTextNotContains('body', $hiddenContent);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}