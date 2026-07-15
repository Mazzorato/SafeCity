<?php

namespace App\Controller;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/city')]
final class CityController extends AbstractController
{
     #[Route('', name: 'app_city_select', methods: ['GET'])]
     public function index(EntityManagerInterface $em, Request $request) : Response 
     {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $search = $request->query->get('query', '');

        $queryBuilder = $em->getRepository(City::class)->createQueryBuilder('c')
            ->where('c.available = true')
            ->orderBy('c.name', 'ASC');

        if ($search !== '') {
            $queryBuilder->andWhere('Lower(c.name) LIKE :search')
                ->setParameter('search','%'. strtolower($search) .'%');
        }

        $cities = $queryBuilder->getQuery()->getResult();

        return $this->render('city/index.html.twig', [
            'cities' => $cities,
            'search' => $search,
        ]);
     }
     #[Route('/choose/{id}', name: 'app_city_choose', methods: ['POST'])]
     public function choose(City $city, EntityManagerInterface $em, Request $request): Response
     {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if(!$this->isCsrfTokenValid('choose_city', $request->request->get('_token'))){
            throw $this->createAccessDeniedException('Token invalide');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setCity($city);
        $em->flush();

        $this->addFlash('success', 'Votre ville a été mise à jour :' . $city->getName());

        return $this->redirectToRoute('app_city_select');
     }
}