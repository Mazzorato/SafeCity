<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\ReportStatusHistory;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\ReportStatusEnum;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie qu’une transition administrateur alimente la chronologie réelle.
 */
final class ReportStatusHistoryTest extends WebTestCase
{
    public function testAdministratorStatusChangeCreatesDatedHistory(): void
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
            $reportedAt = new \DateTime('-5 minutes');

            // Toutes les données sont isolées dans la transaction du test afin
            // de ne dépendre d’aucune fixture et de ne rien conserver ensuite.
            $city = (new City())
                ->setName('Ville historique ' . $suffix)
                ->setPostalCode('31000')
                ->setDepartment('Haute-Garonne')
                ->setAvailable(true);
            $reporter = $this->createUser(
                'reporter-history-' . $suffix . '@example.test',
                RoleEnum::ROLE_USER,
                $city,
                $reportedAt,
            );
            $administrator = $this->createUser(
                'admin-history-' . $suffix . '@example.test',
                RoleEnum::ROLE_ADMIN,
                $city,
                $reportedAt,
            );
            $category = (new ReportCategory())
                ->setName('Historique ' . $suffix)
                ->setDescription('Catégorie temporaire du test.')
                ->setIcon('autre');
            $report = (new Report())
                ->setDescription('Signalement temporaire pour la chronologie.')
                ->setGravityLevel(GravityLevelEnum::LOW)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setLatitude('43.6046520')
                ->setLongitude('1.4442090')
                ->setAddress('Toulouse')
                ->setCreatedAt($reportedAt)
                ->setReporter($reporter)
                ->setCategory($category)
                ->setCity($city);
            $initialHistory = (new ReportStatusHistory())
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setChangedAt(\DateTimeImmutable::createFromMutable($reportedAt))
                ->setChangedBy($reporter);
            $report->addStatusHistory($initialHistory);

            foreach ([$city, $reporter, $administrator, $category, $report, $initialHistory] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $reportId = (int) $report->getId();
            $reporterId = (int) $reporter->getId();
            $administratorId = (int) $administrator->getId();

            $client->loginUser($administrator);
            $crawler = $client->request('GET', '/admin');

            self::assertResponseIsSuccessful();

            // Soumet le vrai formulaire afin de couvrir la sécurité CSRF et le
            // parcours administrateur utilisé dans l’application.
            $form = $crawler->filter(sprintf(
                'form[action="/admin/report/%d/status/in_progress"]',
                $reportId
            ))->form();
            $client->submit($form);

            self::assertResponseRedirects('/admin');

            // Relit l’état réellement persisté après la requête HTTP, sans
            // conserver l’ancienne instance Doctrine présente dans le test.
            $entityManager->clear();
            $updatedReport = $entityManager->getRepository(Report::class)->find($reportId);
            $reporter = $entityManager->getRepository(User::class)->find($reporterId);

            self::assertInstanceOf(Report::class, $updatedReport);
            self::assertInstanceOf(User::class, $reporter);

            $history = $entityManager->getRepository(ReportStatusHistory::class)->findBy(
                ['report' => $updatedReport],
                ['changedAt' => 'ASC', 'id' => 'ASC']
            );

            self::assertCount(2, $history);
            self::assertSame(ReportStatusEnum::REPORTED, $history[0]->getStatus());
            self::assertSame(ReportStatusEnum::IN_PROGRESS, $history[1]->getStatus());
            self::assertSame($administratorId, $history[1]->getChangedBy()?->getId());
            self::assertSame(
                $history[1]->getChangedAt()?->format('Y-m-d H:i:s'),
                $updatedReport->getUpdatedAt()?->format('Y-m-d H:i:s')
            );
            self::assertSame($history[0]->getChangedAt(), $updatedReport->getStatusChangedAt('reported'));
            self::assertSame($history[1]->getChangedAt(), $updatedReport->getStatusChangedAt('in_progress'));
            self::assertSame($history[1]->getId(), $updatedReport->getLatestStatusHistory()?->getId());

            $client->loginUser($reporter);
            $client->request('GET', sprintf('/report/%d/follow-up', $reportId));

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', $history[0]->getChangedAt()?->format('H:i'));
            self::assertSelectorTextContains('body', $history[1]->getChangedAt()?->format('H:i'));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    /**
     * Crée un compte minimal valide pour le scénario fonctionnel.
     */
    private function createUser(
        string $email,
        RoleEnum $role,
        City $city,
        \DateTime $registrationDate,
    ): User {
        return (new User())
            ->setFirstName('Utilisateur')
            ->setLastName('Historique')
            ->setEmail($email)
            ->setPassword('mot-de-passe-haché-de-test')
            ->setRegistrationDate(clone $registrationDate)
            ->setRole($role)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setCity($city)
            ->setIsVerified(true);
    }
}


