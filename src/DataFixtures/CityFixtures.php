<?php

namespace App\DataFixtures;

use App\Entity\City;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CityFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $cities = [
            ['name' => 'Toulouse', 'postalCode' => '31000', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Blagnac', 'postalCode' => '31700', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Colomiers', 'postalCode' => '31770', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Muret', 'postalCode' => '31600', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Tournefeuille', 'postalCode' => '31170', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Cugnaux', 'postalCode' => '31270', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Labège', 'postalCode' => '31520', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Plaisance-du-Touch', 'postalCode' => '31830', 'department' => 'Haute-Garonnne', 'available' => true],
            ['name' => 'Saint-Orens-de-Gameville', 'postalCode' => '31650', 'department' => 'Haute-Garonnne', 'available' => true],
        ];


        foreach ($cities as $data) {
            $city = new City();
            $city->setName($data['name']);
            $city->setPostalCode($data['postalCode']);
            $city->setDepartment($data['department']);
            $city->setAvailable($data['available']);

            $manager->persist($city);
            $this->addReference('city_' . strtolower($data['name']), $city);
        }
        $manager->flush();
    }
}
