<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\User;
use App\Entity\Profile;
use App\Enum\RoleEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Charge les données locales de démonstration gérées par UserFixtures.
 */
class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Le compte administrateur existant est réutilisé sans réinitialiser son
        // mot de passe ni son identifiant de base de données.
        $admin = $manager->getRepository(User::class)->findOneBy([
            'email' => 'admin@safecity.fr',
        ]) ?? new User();
        $admin
            ->setFirstName('Admin')
            ->setLastName('SafeCity')
            ->setEmail('admin@safecity.fr')
            ->setRole(RoleEnum::ROLE_ADMIN)
            ->setCguAccepted(true)
            ->setAccountActive(true)
            ->setIsVerified(true)
            ->setCity($this->getReference('city_toulouse', City::class));
        if ($admin->getPassword() === null || $admin->getPassword() === '') {
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin1234'));
        }
        if ($admin->getRegistrationDate() === null) {
            $admin->setRegistrationDate(new \DateTime());
        }
        if ($admin->getProfile() === null) {
            $admin->setProfile($this->createProfile());
        }
        $manager->persist($admin);

        $cities = ['city_toulouse', 'city_blagnac', 'city_colomiers', 'city_labège', 'city_muret', 'city_tournefeuille', 'city_cugnaux', 'city_plaisance-du-touch', 'city_saint-orens-de-gameville'];
        for ($i = 0; $i < 10; $i++) {
            $email = sprintf('citoyen%02d@safecity.local', $i + 1);
            $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]) ?? new User();
            $user
                ->setFirstName('Citoyen')
                ->setLastName(sprintf('Démo %02d', $i + 1))
                ->setEmail($email)
                ->setRole(RoleEnum::ROLE_USER)
                ->setCguAccepted(true)
                ->setAccountActive(true)
                ->setIsVerified(true)
                ->setCity($this->getReference($cities[$i % count($cities)], City::class));
            if ($user->getPassword() === null || $user->getPassword() === '') {
                $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            }
            if ($user->getRegistrationDate() === null) {
                $user->setRegistrationDate(new \DateTime(sprintf('-%d days', ($i + 1) * 10)));
            }
            if ($user->getProfile() === null) {
                $user->setProfile($this->createProfile());
            }

            $manager->persist($user);
            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixtures::class];
    }

    private function createProfile(): Profile
    {
        // Les préférences explicites rendent ces comptes immédiatement testables.
        return (new Profile())
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(true)
            ->setLocationAccess(true)
            ->setLanguage('fr');
    }
}