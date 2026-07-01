<?php

namespace App\DataFixtures;

use App\Entity\ReportCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ReportCategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            ['name' => 'Accident routier', 'description' => 'Collision, carambolage, véhicule renversé', 'icon' => 'accident'],
            ['name' => 'Travaux/Danger', 'description' => 'Chantier, obstacle sur la voie publique', 'icon' => 'travaux'],
            ['name' => 'Incivilité', 'description' => 'Dégradation, tagage, nuisance sonore', 'icon' => 'incivilite'],
            ['name' => 'Incendie', 'description' => 'Feu de voiture, incendie de bâtiment, feu de poubelle', 'icon' => 'incendie'],
            ['name' => 'Urgence médicale', 'description' => 'Personne blessée, malaise, besoin de secours', 'icon' => 'sante'],
            ['name' => 'Incident routier', 'description' => 'Nid de poule, signalisation défectueuse, route bloquée', 'icon' => 'route'],
            ['name' => 'Autre', 'description' => 'Tout autre type d\'incident non catégorisé', 'icon' => 'autre'],
        ];

        foreach ($categories as $data) {
            $category = new ReportCategory();
            $category->setName($data['name']);
            $category->setDescription($data['description']);
            $category->setIcon($data['icon']);

            $manager->persist($category);
            $this->addReference('category_' . $data['icon'], $category);
        }

        $manager->flush();
    }
}
