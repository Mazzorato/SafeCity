<?php 

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\News;
use App\Enum\NewsCategoryEnum;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NewsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void 
    {
        /** @var City $toulouse  */
        $toulouse = $this->getReference('city_toulouse', City::class);

        $newsData = [
            [
                'title' => 'Renforcement du dispositif de sécurité place du Capitole',
                'content' => 'La mairie annonce un renforcement de la présence policière sur la place du Capitole suite aux récents événements.',
                'category' => NewsCategoryEnum::SECURITE,
                'isFeatured' => true,
            ],
            [
                'title' => 'Fermeture partielle du périphérique',
                'content' => 'Des travaux de maintenance entraînent la fermeture partielle du périphérique toulousain ce week-end.',
                'category' => NewsCategoryEnum::TRAVAUX,
                'isFeatured' => false,
            ],
            [
                'title' => 'Vague de chaleur : ouverture de points de fraîcheur',
                'content' => 'Face à la vigilance canicule, la ville de Toulouse ouvre plusieurs points de fraîcheur accessibles à tous.',
                'category' => NewsCategoryEnum::SANTE,
                'isFeatured' => false,
            ],
            [
                'title' => 'Nouvelle ligne de bus vers Labège',
                'content' => 'Tisséo lance une nouvelle ligne de bus pour desservir le quartier de Labège Innopole.',
                'category' => NewsCategoryEnum::MOBILITE,
                'isFeatured' => false,
            ],
            [
                'title' => 'Collecte des ordures : nouveaux horaires',
                'content' => 'La ville modifie les horaires de collecte des ordures ménagères à partir du mois prochain.',
                'category' => NewsCategoryEnum::TRAVAUX,
                'isFeatured' => false,
            ],
        ];

        foreach ($newsData as $data) {
            $news = new News();
            $news->setTitle($data['title']);
            $news->setContent($data['content']);
            $news->setCategory($data['category']);
            $news->setIsFeatured($data['isFeatured']);
            $news->setPublishedAt(new \DateTime());
            $news->setCity($toulouse);

            $manager->persist($news);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixtures::class];   
    }
}