<?php

namespace App\Tests\Controller;

use App\Entity\City;
use App\Entity\EmergencyService;
use App\Entity\Profile;
use App\Entity\ReportCategory;
use App\Entity\RoutingRule;
use App\Entity\User;
use App\Enum\GravityLevelEnum;
use App\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RoutingRuleControllerTest extends WebTestCase
{
    public function testAdminCanCreateEditAndToggleRoutingRule(): void
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
                ->setName('Ville routage ' . $suffix)
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
            $admin = (new User())
                ->setFirstName('Admin')
                ->setLastName('Routage')
                ->setEmail('routing-admin-' . $suffix . '@example.test')
                ->setPassword('mot-de-passe-haché-de-test')
                ->setRegistrationDate(new \DateTime())
                ->setRole(RoleEnum::ROLE_ADMIN)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setCity($city)
                ->setProfile($profile)
                ->setIsVerified(true);
            $category = (new ReportCategory())
                ->setName('Catégorie routage ' . $suffix)
                ->setDescription('Catégorie temporaire du test de routage.')
                ->setIcon('autre');
            $service = (new EmergencyService())
                ->setName('Service routage ' . $suffix)
                ->setType('municipal')
                ->setPhone('0500000000')
                ->setStatus('active');

            foreach ([$city, $profile, $admin, $category, $service] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();
            $client->loginUser($admin);

           
            $crawler = $client->request('GET', '/admin/routing');
            self::assertResponseIsSuccessful();
            $form = $crawler->filter('form[name="routing_rule"]')->form();
            $form['routing_rule[gravityLevel]']->select(GravityLevelEnum::HIGH->value);
            $form['routing_rule[category]']->select((string) $category->getId());
            $form['routing_rule[emergencyService]']->select((string) $service->getId());
            $form['routing_rule[priority]'] = '25';
            $form['routing_rule[enabled]']->tick();
            $client->submit($form);

            self::assertResponseRedirects('/admin/routing');
            $currentEntityManager = static::getContainer()->get(EntityManagerInterface::class);
            $rule = $currentEntityManager->getRepository(RoutingRule::class)->findOneBy([
                'category' => $category->getId(),
            ]);
            self::assertInstanceOf(RoutingRule::class, $rule);
            self::assertSame(GravityLevelEnum::HIGH, $rule->getGravityLevel());
            self::assertSame(25, $rule->getPriority());
            self::assertTrue($rule->isEnabled());

            
            $crawler = $client->request('GET', '/admin/routing/' . $rule->getId() . '/edit');
            self::assertResponseIsSuccessful();
            $form = $crawler->filter('form[name="routing_rule"]')->form();
            $form['routing_rule[gravityLevel]']->select(GravityLevelEnum::MEDIUM->value);
            $form['routing_rule[priority]'] = '7';
            $client->submit($form);

            self::assertResponseRedirects('/admin/routing');
            $editedRule = static::getContainer()
                ->get(EntityManagerInterface::class)
                ->getRepository(RoutingRule::class)
                ->find($rule->getId());
            self::assertInstanceOf(RoutingRule::class, $editedRule);
            self::assertSame(GravityLevelEnum::MEDIUM, $editedRule->getGravityLevel());
            self::assertSame(7, $editedRule->getPriority());

            
            $crawler = $client->followRedirect();
            $toggleForm = $crawler->filter(sprintf(
                'form[action="/admin/routing/%d/toggle"]',
                $rule->getId(),
            ))->form();
            $client->submit($toggleForm);

            self::assertResponseRedirects('/admin/routing');
            $toggledRule = static::getContainer()
                ->get(EntityManagerInterface::class)
                ->getRepository(RoutingRule::class)
                ->find($rule->getId());
            self::assertInstanceOf(RoutingRule::class, $toggledRule);
            self::assertFalse($toggledRule->isEnabled());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}