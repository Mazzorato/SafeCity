<?php

namespace App\Controller;

use App\Entity\User;

use App\Entity\Parking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ParkingController extends AbstractController
{
#[Route('/parking', name: 'app_parking')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $search = trim($request->query->getString('q'));
        $type = $request->query->getString('type', 'all');
        if (!in_array($type, ['all', 'free', 'paid'], true)) {
            $type = 'all';
        }

        $parkings = [];
        if ($city !== null) {
            $queryBuilder = $em->getRepository(Parking::class)->createQueryBuilder('parking')
                ->where('parking.city = :city')
                ->setParameter('city', $city)
                ->orderBy('parking.availableSpots', 'DESC')
                ->addOrderBy('parking.name', 'ASC');
            if ($search !== '') {
                $queryBuilder
                    ->andWhere('LOWER(parking.name) LIKE :search OR LOWER(parking.address) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }
            if ($type === 'free') {
                $queryBuilder->andWhere('parking.isFree = true');
            } elseif ($type === 'paid') {
                $queryBuilder->andWhere('parking.isFree = false');
            }
            $parkings = $queryBuilder->getQuery()->getResult();
        }

        $mapParkings = array_map(
            static fn (Parking $parking): array => [
                'id' => $parking->getId(),
                'name' => $parking->getName(),
                'address' => $parking->getAddress(),
                'latitude' => (float) $parking->getLatitude(),
                'longitude' => (float) $parking->getLongitude(),
                'free' => $parking->isFree(),
                'availableSpots' => $parking->getAvailableSpots(),
                'totalSpots' => $parking->getTotalSpots(),
            ],
            $parkings,
        );

        return $this->render('parking/index.html.twig', [
            'parkings' => $parkings,
            'mapParkings' => $mapParkings,
            'city' => $city,
            'search' => $search,
            'type' => $type,
        ]);
    }

}


