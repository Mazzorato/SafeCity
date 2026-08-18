<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Event;
use App\Enum\EventCategoryEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Charge un agenda local fictif, gratuit et sans fournisseur extérieur.
 */
final class EventFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        // Le même groupe que les villes garantit un chargement ciblé et ordonné.
        return ['safecity_demo_content'];
    }

    public function load(ObjectManager $manager): void
    {
        $events = [
            ['city' => 'Toulouse', 'title' => 'Concert local de la Garonne', 'description' => 'Une soirée musicale fictive en plein air pour découvrir des artistes locaux.', 'location' => 'Prairie des Filtres', 'days' => 7, 'time' => [19, 30], 'hours' => 3, 'free' => true, 'category' => EventCategoryEnum::MUSIQUE],
            ['city' => 'Toulouse', 'title' => 'Course citoyenne du Capitole', 'description' => 'Un parcours sportif fictif et accessible au cœur de Toulouse.', 'location' => 'Place du Capitole', 'days' => 14, 'time' => [9, 0], 'hours' => 4, 'free' => true, 'category' => EventCategoryEnum::SPORT],
            ['city' => 'Toulouse', 'title' => 'Visite nocturne des Augustins', 'description' => 'Une visite culturelle fictive consacrée au patrimoine toulousain.', 'location' => 'Musée des Augustins', 'days' => 21, 'time' => [20, 0], 'hours' => 2, 'free' => false, 'category' => EventCategoryEnum::CULTURE],
            ['city' => 'Blagnac', 'title' => 'Forum culturel de Blagnac', 'description' => 'Une rencontre fictive avec les associations culturelles locales.', 'location' => 'Odyssud', 'days' => 10, 'time' => [14, 0], 'hours' => 4, 'free' => true, 'category' => EventCategoryEnum::CULTURE],
            ['city' => 'Colomiers', 'title' => 'Tournoi sportif de Colomiers', 'description' => 'Un tournoi multisport fictif ouvert aux habitants.', 'location' => 'Complexe sportif Capitany', 'days' => 12, 'time' => [10, 0], 'hours' => 6, 'free' => true, 'category' => EventCategoryEnum::SPORT],
            ['city' => 'Muret', 'title' => 'Scène musicale de Muret', 'description' => 'Un concert fictif réunissant plusieurs groupes de la région.', 'location' => 'Parc Jean-Jaurès', 'days' => 16, 'time' => [18, 30], 'hours' => 3, 'free' => true, 'category' => EventCategoryEnum::MUSIQUE],
            ['city' => 'Tournefeuille', 'title' => 'Rencontre culturelle de Tournefeuille', 'description' => 'Une journée fictive dédiée aux arts et aux initiatives citoyennes.', 'location' => 'Le Phare', 'days' => 18, 'time' => [15, 0], 'hours' => 4, 'free' => true, 'category' => EventCategoryEnum::CULTURE],
            ['city' => 'Cugnaux', 'title' => 'Journée sportive de Cugnaux', 'description' => 'Des ateliers sportifs fictifs pour tous les âges.', 'location' => 'Plaine des sports', 'days' => 20, 'time' => [9, 30], 'hours' => 6, 'free' => true, 'category' => EventCategoryEnum::SPORT],
            ['city' => 'Labège', 'title' => 'Festival local de Labège', 'description' => 'Un rendez-vous musical fictif organisé dans le centre-ville.', 'location' => 'Place Saint-Barthélemy', 'days' => 22, 'time' => [19, 0], 'hours' => 3, 'free' => true, 'category' => EventCategoryEnum::MUSIQUE],
            ['city' => 'Plaisance-du-Touch', 'title' => 'Concert au Touch', 'description' => 'Un concert familial fictif au bord du Touch.', 'location' => 'Espace Monestié', 'days' => 24, 'time' => [18, 0], 'hours' => 3, 'free' => true, 'category' => EventCategoryEnum::MUSIQUE],
            ['city' => 'Saint-Orens-de-Gameville', 'title' => 'Exposition citoyenne de Saint-Orens', 'description' => 'Une exposition fictive créée avec les associations de la ville.', 'location' => 'Altigone', 'days' => 26, 'time' => [14, 0], 'hours' => 5, 'free' => true, 'category' => EventCategoryEnum::CULTURE],
        ];

        foreach ($events as $data) {
            /** @var City|null $city */
            $city = $manager->getRepository(City::class)->findOneBy(['name' => $data['city']]);
            if ($city === null) {
                throw new \RuntimeException(sprintf('La ville « %s » est requise pour charger les événements.', $data['city']));
            }

            // Le titre et la ville forment la clé fonctionnelle des données de démonstration.
            $event = $manager->getRepository(Event::class)->findOneBy([
                'title' => $data['title'],
                'city' => $city,
            ]) ?? new Event();
            $startedAt = (new \DateTime('today'))
                ->modify(sprintf('+%d days', $data['days']))
                ->setTime($data['time'][0], $data['time'][1]);
            $endedAt = (clone $startedAt)->modify(sprintf('+%d hours', $data['hours']));

            $event
                ->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setLocation($data['location'])
                ->setStartedAt($startedAt)
                ->setEndedAt($endedAt)
                ->setLatitude($city->getLatitude())
                ->setLongitude($city->getLongitude())
                ->setIsFree($data['free'])
                ->setImageUrl(null)
                ->setCategory($data['category'])
                ->setCity($city);
            $manager->persist($event);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CityFixtures::class];
    }
}


