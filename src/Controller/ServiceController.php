<?php

namespace App\Controller;

use App\Entity\LocalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceController extends AbstractController
{
    #[Route('/service', name: 'app_service')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $search = $request->query->get('query', '');

        $servicesByType= [];
        $cityHall = null;

        if ($city) {
            $queryBuilder = $em->getRepository(LocalService::class)->createQueryBuilder('s')
                ->where('s.city = :city')
                ->setParameter('city', $city);

            if ($search !== '') {
                $queryBuilder->andWhere('Lower(s.name) LIKE :search')
                    ->setParameter('search','%'. strtolower($search) .'%');
            }

            $services = $queryBuilder->getQuery()->getResult();
            
            foreach ($services as $service) {
                if ($service->getType()->value === 'city_hall' && $cityHall === null) {
                    }
                    $servicesByType[$service->getType()->value][] = $service;
            }
        }
        return $this->render('service/index.html.twig', [
            'servicesByType' => $servicesByType,
            'cityHall' => $cityHall,
            'city' => $city,
            'search' => $search,
        ]);
    }
}
