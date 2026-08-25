<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\CityFixtures;
use App\DataFixtures\EmergencyServiceFixtures;
use App\DataFixtures\ReportCategoryFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\EmergencyService;
use App\Entity\ReportCategory;
use App\Entity\User;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Vérifie que les référentiels et comptes fictifs sont réutilisés à l'identique.
 */
final class ReferenceAndUserFixturesTest extends KernelTestCase
{
    public function testReferenceAndUserFixturesAreIdempotent(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('L’extension PHP pdo_pgsql est nécessaire au test des fixtures.');
        }

        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->loadFixtures($entityManager, $passwordHasher);
            $countsAfterFirstLoad = $this->counts($entityManager);
            $admin = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@safecity.fr']);
            self::assertNotNull($admin);
            $adminId = $admin->getId();

            $this->loadFixtures($entityManager, $passwordHasher);

            self::assertSame($countsAfterFirstLoad, $this->counts($entityManager));
            self::assertSame(
                $adminId,
                $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@safecity.fr'])?->getId()
            );

            foreach (['police', 'pompiers', 'samu', 'municipale', 'gendarmerie'] as $type) {
                self::assertSame(1, $entityManager->getRepository(EmergencyService::class)->count(['type' => $type]));
            }
            foreach (['accident', 'travaux', 'incivilite', 'incendie', 'sante', 'route', 'autre'] as $icon) {
                self::assertSame(1, $entityManager->getRepository(ReportCategory::class)->count(['icon' => $icon]));
            }
            for ($index = 1; $index <= 10; ++$index) {
                self::assertSame(1, $entityManager->getRepository(User::class)->count([
                    'email' => sprintf('citoyen%02d@safecity.local', $index),
                ]));
            }
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    private function loadFixtures(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): void {
        $references = new ReferenceRepository($entityManager);
        $fixtures = [
            new CityFixtures(),
            new EmergencyServiceFixtures(),
            new ReportCategoryFixtures(),
            new UserFixtures($passwordHasher),
        ];

        foreach ($fixtures as $fixture) {
            $fixture->setReferenceRepository($references);
            $fixture->load($entityManager);
        }
    }

    /**
     * @return array{services: int, categories: int, users: int}
     */
    private function counts(EntityManagerInterface $entityManager): array
    {
        return [
            'services' => $entityManager->getRepository(EmergencyService::class)->count([]),
            'categories' => $entityManager->getRepository(ReportCategory::class)->count([]),
            'users' => $entityManager->getRepository(User::class)->count([]),
        ];
    }
}