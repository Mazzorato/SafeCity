<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\User;

use App\Entity\Event;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
#[Route('/home', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $events = $city === null ? [] : $entityManager->getRepository(Event::class)->createQueryBuilder('event')
            ->where('event.city = :city')
            ->andWhere('event.startedAt >= :now')
            ->setParameter('city', $city)
            ->setParameter('now', new \DateTime())
            ->orderBy('event.startedAt', 'ASC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'events' => $events,
        ]);
    }

    #
}


