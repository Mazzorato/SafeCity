<?php

namespace App\DataFixtures;

use App\Entity\EmergencyService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge les données locales de démonstration gérées par EmergencyServiceFixtures.
 */
class EmergencyServiceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $services = [
            ['name' => 'Police Nationale Toulouse', 'type' => 'police', 'phone' => '17', 'status' => 'active' ],
            ['name' => 'Pompiers Toulouse', 'type' => 'pompiers', 'phone' => '18', 'status' => 'active' ],
            ['name' => 'Samu Toulouse', 'type' => 'samu', 'phone' => '15', 'status' => 'active'],
            ['name' => 'Service Municipale Toulouse', 'type' => 'municipale', 'phone' => '05 61 22 22 22', 'status' => 'active'],
            ['name' => 'Gendarmerie Haute-Garonne', 'type' => 'gendarmerie', 'phone' => '05 61 33 33 33', 'status' => 'active'],

        ];

        foreach ($services as $data) {
            // Le type constitue la clé stable de ces cinq destinataires locaux.
            $service = $manager->getRepository(EmergencyService::class)->findOneBy([
                'type' => $data['type'],
            ]) ?? new EmergencyService();
            $service
                ->setName($data['name'])
                ->setType($data['type'])
                ->setPhone($data['phone'])
                ->setStatus($data['status']);

            $manager->persist($service);
            $this->addReference('emergency_service_' . $data['type'], $service);
        }

        $manager->flush();
    }
}


