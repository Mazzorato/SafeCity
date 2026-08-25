<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\LocalService;
use App\Entity\Report;
use App\Entity\User;
use App\Service\TisseoOpenDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Assemble les données du tableau de bord citoyen.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_root', methods: ['GET'])]
    public function root(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/home', name: 'app_home')]
    public function index(
        EntityManagerInterface $entityManager,
        TisseoOpenDataClient $tisseoOpenData,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        if ($city === null) {
            return $this->redirectToRoute('app_city_select');
        }

        $reports = $entityManager->getRepository(Report::class)->findBy(
            ['city' => $city],
            ['createdAt' => 'DESC'],
            2
        );
        // Le tableau de bord reprend le même sous-ensemble Tisséo que la page
        // mobilité afin d'éviter deux sources contradictoires.
        $transportNetwork = $tisseoOpenData->getNetwork();
        $transports = array_slice($transportNetwork['lines'], 0, 4);
        $events = $entityManager->getRepository(Event::class)->createQueryBuilder('event')
            ->where('event.city = :city')
            ->andWhere('event.startedAt >= :now')
            ->setParameter('city', $city)
            ->setParameter('now', new \DateTime())
            ->orderBy('event.startedAt', 'ASC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();
        $localServices = $entityManager->getRepository(LocalService::class)->findBy(
            ['city' => $city],
            ['name' => 'ASC'],
            3
        );

        return $this->render('home/index.html.twig', [
            'city' => $city,
            'reports' => $reports,
            'transports' => $transports,
            'transportDataAvailable' => $transportNetwork['available'],
            'events' => $events,
            'localServices' => $localServices,
        ]);
    }
}