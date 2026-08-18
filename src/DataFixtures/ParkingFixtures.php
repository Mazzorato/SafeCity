<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Parking;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge des parkings locaux permettant de tester les recherches de proximité.
 */
final class ParkingFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        // Ce groupe permet un rechargement ciblé avec --append, sans comptes fictifs.
        return ['safecity_parking_content', 'safecity_demo_content'];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var City $toulouse */
        $toulouse = $this->getReference('city_toulouse', City::class);

        $parkingsData = [
            [
                'name' => 'Parking Capitole',
                'address' => 'Place du Capitole, 31000 Toulouse',
                'latitude' => '43.6045000',
                'longitude' => '1.4440000',
                'free' => false,
                'hourlyRate' => '1.80',
                'availableSpots' => 126,
                'totalSpots' => 857,
            ],
            [
                'name' => 'Parking Esquirol',
                'address' => '14 Place Étienne Esquirol, 31000 Toulouse',
                'latitude' => '43.6003500',
                'longitude' => '1.4456500',
                'free' => false,
                'hourlyRate' => '1.60',
                'availableSpots' => 48,
                'totalSpots' => 405,
            ],
            [
                'name' => 'Parking Saint-Cyprien',
                'address' => 'Place intérieure Saint-Cyprien, 31300 Toulouse',
                'latitude' => '43.5986500',
                'longitude' => '1.4312500',
                'free' => false,
                'hourlyRate' => '1.40',
                'availableSpots' => 72,
                'totalSpots' => 300,
            ],
            [
                'name' => 'Parking relais Jolimont',
                'address' => 'Avenue Yves Brunaud, 31500 Toulouse',
                'latitude' => '43.6156000',
                'longitude' => '1.4631000',
                'free' => true,
                'hourlyRate' => '0.00',
                'availableSpots' => 214,
                'totalSpots' => 350,
            ],
            [
                'name' => 'Parking relais Arènes',
                'address' => 'Place Agapito Nadal, 31100 Toulouse',
                'latitude' => '43.5935500',
                'longitude' => '1.4186500',
                'free' => true,
                'hourlyRate' => '0.00',
                'availableSpots' => 96,
                'totalSpots' => 600,
            ],
            [
                'name' => 'Parking relais Basso Cambo',
                'address' => '5 Avenue Louis Bazerque, 31100 Toulouse',
                'latitude' => '43.5707000',
                'longitude' => '1.3927000',
                'free' => true,
                'hourlyRate' => '0.00',
                'availableSpots' => 382,
                'totalSpots' => 540,
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
            $latitude = (float) $city->getLatitude();
            $longitude = (float) $city->getLongitude();
            $postalCode = (string) $city->getPostalCode();
            $cityName = (string) $city->getName();
            // Trois états fictifs (disponible, relais gratuit et complet)
            // préparent aussi la recherche d'alternatives du prochain groupe.
            $definitions = [
                ['name' => 'Parking centre ' . $cityName, 'address' => '1 Place Centrale, ' . $postalCode . ' ' . $cityName, 'free' => false, 'hourlyRate' => '1.50', 'availableSpots' => 34, 'totalSpots' => 120, 'offset' => [0.0006, 0.0005]],
                ['name' => 'Parking relais ' . $cityName, 'address' => '2 Avenue des Mobilités, ' . $postalCode . ' ' . $cityName, 'free' => true, 'hourlyRate' => '0.00', 'availableSpots' => 96, 'totalSpots' => 180, 'offset' => [-0.0020, 0.0015]],
                ['name' => 'Parking gare ' . $cityName, 'address' => '3 Boulevard de la Gare, ' . $postalCode . ' ' . $cityName, 'free' => false, 'hourlyRate' => '1.20', 'availableSpots' => 0, 'totalSpots' => 80, 'offset' => [0.0018, -0.0014]],
            ];

            foreach ($definitions as $definition) {
                $parkingsData[] = [
                    ...$definition,
                    'latitude' => number_format($latitude + $definition['offset'][0], 7, '.', ''),
                    'longitude' => number_format($longitude + $definition['offset'][1], 7, '.', ''),
                    'city' => $city,
                ];
            }
        }

        foreach ($parkingsData as $data) {
            // Les disponibilités sont fictives et servent uniquement à rendre
            // l'interface locale vérifiable après chargement des fixtures.
            /** @var City $city */
            $city = $data['city'] ?? $toulouse;
            $parking = $manager->getRepository(Parking::class)->findOneBy([
                'name' => $data['name'],
                'city' => $city,
            ]) ?? new Parking();
            $parking
                ->setName($data['name'])
                ->setAddress($data['address'])
                ->setLatitude($data['latitude'])
                ->setLongitude($data['longitude'])
                ->setIsFree($data['free'])
                ->setHourlyRate($data['hourlyRate'])
                ->setAvailableSpots($data['availableSpots'])
                ->setTotalSpots($data['totalSpots'])
                ->setCity($city);

            $manager->persist($parking);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixtures::class];
    }
}


