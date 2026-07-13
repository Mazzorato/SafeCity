<?php 

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\LocalService;
use App\Enum\ServiceTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class LocalServiceFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @®var City $toulouse */
        $toulouse = $this->getReference('city_toulouse', City::class);

        $servicesData = [
            [
                'name' => 'Mairie Centrale',
                'address' => 'Place du Capitole, 31000 Toulouse',
                'type' => ServiceTypeEnum::CITY_HALL,
                'phone' => '05 61 22 22 22',
                'openingHours' => 'Lundi au Vendredi, 8h30 - 17h',
                'onDuty' => true,
            ],
            [
                'name' => 'Bibliothèque Municipale',
                'address' => '1 Rue de Périgord, 31000 Toulouse',
                'type' => ServiceTypeEnum::LIBRARY,
                'phone' => '05 61 22 31 31',
                'openingHours' => 'Mardi au Samedi, 10h - 18h',
                'onDuty' => false,
            ],
            [
                'name' => 'Centre Médical Municipal',
                'address' => '12 Rue Alsace-Lorraine, 31000 Toulouse',
                'type' => ServiceTypeEnum::HEALTH,
                'phone' => '05 61 22 44 44',
                'openingHours' => 'Lundi au Vendredi, 9h - 19h',
                'onDuty' => true,
            ],
            [
                'name' => 'École Jules Ferry',
                'address' => '5 Avenue des Écoles, 31000 Toulouse',
                'type' => ServiceTypeEnum::EDUCATION,
                'phone' => '05 61 22 55 55',
                'openingHours' => 'Lundi au Vendredi, 8h - 16h30',
                'onDuty' => false,
            ],
            [
                'name' => 'Service Urbanisme',
                'address' => '3 Place Saint-Étienne, 31000 Toulouse',
                'type' => ServiceTypeEnum::URBAN_PLANNING,
                'phone' => '05 61 22 66 66',
                'openingHours' => 'Lundi au Vendredi, 8h30 - 17h',
                'onDuty' => false,
            ],
        ];

        foreach ($servicesData as $data) {
            $service = new LocalService();
            $service->setName($data['name']);
            $service->setAddress($data['address']);
            $service->setType($data['type']);
            $service->setPhone($data['phone']);
            $service->setOpeningHours($data['openingHours']);
            $service->setOnDuty($data['onDuty']);
            $service->setLatitude('43.6045000');
            $service->setLongitude('1.4442000');
            $service->setCity($toulouse);

            $manager->persist($service);
        }
        $manager->flush();
    }
    public function getDependencies(): array
    {
        return [ CityFixtures::class];
    }

}
