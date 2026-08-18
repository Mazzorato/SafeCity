<?php 

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\News;
use App\Enum\NewsCategoryEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge les données locales de démonstration gérées par NewsFixtures.
 */
class NewsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['safecity_demo_content'];
    }

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

        $otherCities = $manager->getRepository(City::class)->createQueryBuilder('city')
            ->where('city.available = true')
            ->andWhere('city.name != :toulouse')
            ->setParameter('toulouse', 'Toulouse')
            ->orderBy('city.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($otherCities as $city) {
            // Ces annonces sont explicitement fictives et servent uniquement à
            // éviter un écran vide pour les villes de démonstration.
            $newsData[] = [
                'title' => 'Point citoyen à ' . $city->getName(),
                'content' => 'La ville présente une permanence citoyenne fictive consacrée aux démarches et aux initiatives locales.',
                'category' => NewsCategoryEnum::SECURITE,
                'isFeatured' => true,
                'city' => $city,
            ];
            $newsData[] = [
                'title' => 'Aménagements de mobilité à ' . $city->getName(),
                'content' => 'Des aménagements fictifs sont annoncés afin de tester les informations de mobilité de proximité.',
                'category' => NewsCategoryEnum::MOBILITE,
                'isFeatured' => false,
                'city' => $city,
            ];
        }

        foreach ($newsData as $data) {
            /** @var City $city */
            $city = $data['city'] ?? $toulouse;
            $news = $manager->getRepository(News::class)->findOneBy([
                'title' => $data['title'],
                'city' => $city,
            ]) ?? new News();
            $news
                ->setTitle($data['title'])
                ->setContent($data['content'])
                ->setCategory($data['category'])
                ->setIsFeatured($data['isFeatured'])
                ->setSource('Données fictives SafeCity')
                ->setLatitude($city->getLatitude())
                ->setLongitude($city->getLongitude())
                ->setImageUrl(null)
                ->setCity($city);
            if ($news->getPublishedAt() === null) {
                // Une réexécution ne modifie pas artificiellement l'ordre chronologique.
                $news->setPublishedAt(new \DateTime());
            }

            $manager->persist($news);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixtures::class];   
    }
}


