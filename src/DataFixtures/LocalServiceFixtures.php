<?php 

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\LocalService;
use App\Enum\ServiceTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge les données locales de démonstration gérées par LocalServiceFixtures.
 */
class LocalServiceFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        // Ce groupe ne contient que du contenu local et ne charge aucun compte.
        return ['safecity_demo_content'];
    }

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
                'latitude' => '43.6042700',
                'longitude' => '1.4436700',
            ],
            [
                'name' => 'Bibliothèque Municipale',
                'address' => '1 Rue de Périgord, 31000 Toulouse',
                'type' => ServiceTypeEnum::LIBRARY,
                'phone' => '05 61 22 31 31',
                'openingHours' => 'Mardi au Samedi, 10h - 18h',
                'onDuty' => false,
                'latitude' => '43.6081500',
                'longitude' => '1.4408200',
            ],
            [
                'name' => 'Centre Médical Municipal',
                'address' => '12 Rue Alsace-Lorraine, 31000 Toulouse',
                'type' => ServiceTypeEnum::HEALTH,
                'phone' => '05 61 22 44 44',
                'openingHours' => 'Lundi au Vendredi, 9h - 19h',
                'onDuty' => true,
                'latitude' => '43.6047300',
                'longitude' => '1.4451200',
            ],
            [
                'name' => 'Cabinet médical Saint-Cyprien',
                'address' => '18 Place intérieure Saint-Cyprien, 31300 Toulouse',
                'type' => ServiceTypeEnum::HEALTH,
                'phone' => '05 61 22 44 45',
                'openingHours' => 'Lundi au Samedi, 8h - 20h',
                'onDuty' => false,
                'latitude' => '43.5988200',
                'longitude' => '1.4312300',
            ],
            [
                'name' => 'Maison de santé des Minimes',
                'address' => '42 Avenue des Minimes, 31200 Toulouse',
                'type' => ServiceTypeEnum::HEALTH,
                'phone' => '05 61 22 44 46',
                'openingHours' => 'Tous les jours, 8h - 22h',
                'onDuty' => true,
                'latitude' => '43.6204100',
                'longitude' => '1.4354300',
            ],
            [
                'name' => 'Pharmacie du Capitole',
                'address' => '8 Place du Capitole, 31000 Toulouse',
                'type' => ServiceTypeEnum::HEALTH,
                'phone' => '05 61 22 44 47',
                'openingHours' => 'Ouvert 24 h/24',
                'onDuty' => true,
                'latitude' => '43.6045500',
                'longitude' => '1.4439600',
            ],
            [
                'name' => 'Pharmacie Saint-Cyprien',
                'address' => '27 Avenue Étienne Billières, 31300 Toulouse',
                'type' => ServiceTypeEnum::HEALTH,
                'phone' => '05 61 22 44 48',
                'openingHours' => 'Lundi au Samedi, 8h30 - 20h',
                'onDuty' => false,
                'latitude' => '43.5972700',
                'longitude' => '1.4249000',
            ],
            [
                'name' => 'École Jules Ferry',
                'address' => '5 Avenue des Écoles, 31000 Toulouse',
                'type' => ServiceTypeEnum::EDUCATION,
                'phone' => '05 61 22 55 55',
                'openingHours' => 'Lundi au Vendredi, 8h - 16h30',
                'onDuty' => false,
                'latitude' => '43.6069100',
                'longitude' => '1.4486700',
            ],
            [
                'name' => 'Service Urbanisme',
                'address' => '3 Place Saint-Étienne, 31000 Toulouse',
                'type' => ServiceTypeEnum::URBAN_PLANNING,
                'phone' => '05 61 22 66 66',
                'openingHours' => 'Lundi au Vendredi, 8h30 - 17h',
                'onDuty' => false,
                'latitude' => '43.6009200',
                'longitude' => '1.4512500',
            ],
        ];

        $otherCities = $manager->getRepository(City::class)->createQueryBuilder('city')
            ->where('city.available = true')
            ->andWhere('city.name != :toulouse')
            ->setParameter('toulouse', 'Toulouse')
            ->orderBy('city.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($otherCities as $cityIndex => $city) {
            // Six établissements fictifs rendent les parcours municipaux et les
            // alternatives médicales ou pharmaceutiques vérifiables localement.
            $cityName = (string) $city->getName();
            $postalCode = (string) $city->getPostalCode();
            $latitude = (float) $city->getLatitude();
            $longitude = (float) $city->getLongitude();
            $localDefinitions = [
                ['name' => 'Mairie de ' . $cityName, 'address' => '1 Place de la Mairie, ' . $postalCode . ' ' . $cityName, 'type' => ServiceTypeEnum::CITY_HALL, 'openingHours' => 'Lundi au vendredi, 8h30 - 17h', 'onDuty' => false, 'offset' => [0.0003, 0.0002]],
                ['name' => 'Médiathèque de ' . $cityName, 'address' => '2 Rue de la Culture, ' . $postalCode . ' ' . $cityName, 'type' => ServiceTypeEnum::LIBRARY, 'openingHours' => 'Mardi au samedi, 10h - 18h', 'onDuty' => false, 'offset' => [-0.0005, 0.0004]],
                ['name' => 'Pharmacie de garde de ' . $cityName, 'address' => '3 Avenue de la Santé, ' . $postalCode . ' ' . $cityName, 'type' => ServiceTypeEnum::HEALTH, 'openingHours' => 'Ouvert 24 h/24', 'onDuty' => true, 'offset' => [0.0007, -0.0006]],
                ['name' => 'Maison médicale de garde de ' . $cityName, 'address' => '4 Allée des Soins, ' . $postalCode . ' ' . $cityName, 'type' => ServiceTypeEnum::HEALTH, 'openingHours' => 'Tous les jours, 18h - 00h', 'onDuty' => true, 'offset' => [-0.0008, -0.0005]],
                ['name' => 'Pharmacie du centre de ' . $cityName, 'address' => '5 Rue du Centre, ' . $postalCode . ' ' . $cityName, 'type' => ServiceTypeEnum::HEALTH, 'openingHours' => 'Lundi au samedi, 8h30 - 19h30', 'onDuty' => false, 'offset' => [0.0011, 0.0008]],
                ['name' => 'Cabinet médical municipal de ' . $cityName, 'address' => '6 Rue des Soins, ' . $postalCode . ' ' . $cityName, 'type' => ServiceTypeEnum::HEALTH, 'openingHours' => 'Lundi au vendredi, 8h - 19h', 'onDuty' => false, 'offset' => [-0.0011, 0.0009]],
            ];

            foreach ($localDefinitions as $serviceIndex => $definition) {
                $servicesData[] = [
                    ...$definition,
                    'phone' => sprintf('05 00 %02d %02d %02d', $cityIndex + 10, $serviceIndex + 10, $serviceIndex + 20),
                    'latitude' => number_format($latitude + $definition['offset'][0], 7, '.', ''),
                    'longitude' => number_format($longitude + $definition['offset'][1], 7, '.', ''),
                    'city' => $city,
                ];
            }
        }

        foreach ($servicesData as $data) {
            /** @var City $city */
            $city = $data['city'] ?? $toulouse;
            $service = $manager->getRepository(LocalService::class)->findOneBy([
                'name' => $data['name'],
                'city' => $city,
            ]) ?? new LocalService();
            $service
                ->setName($data['name'])
                ->setAddress($data['address'])
                ->setType($data['type'])
                ->setPhone($data['phone'])
                ->setOpeningHours($data['openingHours'])
                ->setOnDuty($data['onDuty']);
            // Des coordonnées distinctes rendent les scénarios de proximité
            // vérifiables sans fournisseur de données externe.
            $service
                ->setLatitude($data['latitude'])
                ->setLongitude($data['longitude'])
                ->setCity($city);

            $manager->persist($service);
        }
        $manager->flush();
    }
    public function getDependencies(): array
    {
        return [CityFixtures::class];
    }

}


