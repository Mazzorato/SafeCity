<?php

namespace App\DataFixtures;

use App\Entity\City;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge les données locales de démonstration gérées par CityFixtures.
 */
class CityFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        // Ce groupe ciblé peut être chargé avec --append sans exécuter les comptes.
        return ['safecity_demo_content', 'safecity_parking_content'];
    }

    public function load(ObjectManager $manager): void
    {
        $cities = [
            ['name' => 'Toulouse', 'postalCode' => '31000', 'latitude' => '43.6046520', 'longitude' => '1.4442090'],
            ['name' => 'Blagnac', 'postalCode' => '31700', 'latitude' => '43.6350000', 'longitude' => '1.3900000'],
            ['name' => 'Colomiers', 'postalCode' => '31770', 'latitude' => '43.6139000', 'longitude' => '1.3359000'],
            ['name' => 'Muret', 'postalCode' => '31600', 'latitude' => '43.4603000', 'longitude' => '1.3266000'],
            ['name' => 'Tournefeuille', 'postalCode' => '31170', 'latitude' => '43.5847000', 'longitude' => '1.3442000'],
            ['name' => 'Cugnaux', 'postalCode' => '31270', 'latitude' => '43.5378000', 'longitude' => '1.3447000'],
            ['name' => 'Labège', 'postalCode' => '31520', 'latitude' => '43.5304000', 'longitude' => '1.3989000'],
            ['name' => 'Plaisance-du-Touch', 'postalCode' => '31830', 'latitude' => '43.5658000', 'longitude' => '1.2978000'],
            ['name' => 'Saint-Orens-de-Gameville', 'postalCode' => '31650', 'latitude' => '43.5514000', 'longitude' => '1.5343000'],
        ];

        foreach ($cities as $data) {
            // La recherche par nom évite tout doublon lors des rechargements ciblés.
            $city = $manager->getRepository(City::class)->findOneBy(['name' => $data['name']])
                ?? new City();
            $city
                ->setName($data['name'])
                ->setPostalCode($data['postalCode'])
                ->setDepartment('Haute-Garonne')
                ->setAvailable(true)
                ->setLatitude($data['latitude'])
                ->setLongitude($data['longitude']);

            $manager->persist($city);
            $this->addReference('city_' . mb_strtolower($data['name']), $city);
        }

        $manager->flush();
    }
}
