<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\User;
use App\Entity\Profile;
use App\Enum\RoleEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Compte admin fixe
        $admin = new User();
        $admin->setFirstName('Admin');
        $admin->setLastName('SafeCity');
        $admin->setEmail('admin@safecity.fr');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin1234'));
        $admin->setRole(RoleEnum::ROLE_ADMIN);
        $admin->setRegistrationDate(new \DateTime());
        $admin->setCguAccepted(true);
        $admin->setAccountActive(true);
        $admin->setIsVerified(true);
        $admin->setCity($this->getReference('city_toulouse', City::class));
        $manager->persist($admin);

        // 10 utilisateurs avec Faker
        for ($i = 0; $i < 10; $i++) {
            $profile = new Profile();
            $profile->setEmergencyNotifications(true);
            $profile->setWeatherNotifications($faker->boolean());
            $profile->setTransportNotifications($faker->boolean());
            $profile->setEventNotifications($faker->boolean());
            $profile->setMicrophoneAccess(true);
            $profile->setCameraAccess(true);
            $profile->setLocationAccess(true);
            $profile->setLanguage('fr');
            $manager->persist($profile);

            $user = new User();
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setEmail($faker->unique()->safeEmail());
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setRole(RoleEnum::ROLE_USER);
            $user->setRegistrationDate($faker->dateTimeBetween('-6 months', 'now'));
            $user->setCguAccepted(true);
            $user->setAccountActive(true);
            $user->setIsVerified(true);
            $user->setProfile($profile);

            // Ville aléatoire parmi les disponibles
            $cities = ['city_toulouse', 'city_blagnac', 'city_colomiers', 'city_labège', 'city_muret', 'city_tournefeuille', 'city_cugnaux', 'city_plaisance-du-touch', 'city_saint-orens-de-gameville'];
            $user->setCity($this->getReference($cities[array_rand($cities)], City::class));

            $manager->persist($user);
            $this->addReference('user_' . $i, $user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixtures::class];
    }
}