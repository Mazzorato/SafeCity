<?php

namespace App\Controller;

use App\Enum\ServiceTypeEnum;

use App\Entity\User;

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

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $search = trim($request->query->getString('query'));

        $servicesByType= [];
        $cityHall = null;

        if ($city) {
            $queryBuilder = $em->getRepository(LocalService::class)->createQueryBuilder('s')
                ->where('s.city = :city')
                ->setParameter('city', $city);

            if ($search !== '') {
                $queryBuilder
                    ->andWhere('LOWER(s.name) LIKE :search OR LOWER(s.address) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            $services = $queryBuilder
                ->orderBy('s.type', 'ASC')
                ->addOrderBy('s.name', 'ASC')
                ->getQuery()
                ->getResult();
            
            foreach ($services as $service) {
                if ($service->getType()->value === 'city_hall' && $cityHall === null) {
                    $cityHall = $service;
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


#[Route('/service/city', name: 'app_service_city', methods: ['GET'])]
    public function cityServices(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $search = trim($request->query->getString('query'));
        $servicesByType = [];

        if ($city !== null) {
            $queryBuilder = $entityManager->getRepository(LocalService::class)->createQueryBuilder('service')
                ->where('service.city = :city')
                ->andWhere('service.type != :health')
                ->setParameter('city', $city)
                ->setParameter('health', ServiceTypeEnum::HEALTH)
                ->orderBy('service.type', 'ASC')
                ->addOrderBy('service.name', 'ASC');

            if ($search !== '') {
                $queryBuilder
                    ->andWhere('LOWER(service.name) LIKE :search OR LOWER(service.address) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            foreach ($queryBuilder->getQuery()->getResult() as $service) {
                $servicesByType[$service->getType()->value][] = $service;
            }
        }

        return $this->render('service/city.html.twig', [
            'servicesByType' => $servicesByType,
            'city' => $city,
            'search' => $search,
        ]);
    }
}


