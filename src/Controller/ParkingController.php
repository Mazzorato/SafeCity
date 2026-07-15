<?php

namespace App\Controller;

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

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $search = $request->query->get('q', '');
        $type = $request->query->get('type', 'all');

        $parkings = [];

        if ($city) {
            $queryBuilder = $em->getRepository(Parking::class)->createQueryBuilder('p')
                ->where('p.city = :city')
                ->setParameter('city', $city);

            if ($search !== '') {
            $queryBuilder->andWhere('LOWER(p.name) LIKE :search')
                ->setParameter('search','%'. strtolower($search) .'%');
            }

            if ($type === 'free') {
                $queryBuilder->andWhere('p.isFree = true');
            } elseif ($type === 'paid') {
                $queryBuilder->andWhere('p.isFree = false');
            }
            $parkings = $queryBuilder->getQuery()->getResult();
        }

        return $this->render('parking/index.html.twig', [
            'parkings' => $parkings,
            'city' => $city,
            'search'=> $search,
            'type' => $type
        ]);
    }
}
