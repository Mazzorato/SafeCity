<?php

namespace App\Controller;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/city')]
/**
 * Gère la sélection et la détection de la ville courante.
 */
final class CityController extends AbstractController
{
     #[Route('', name: 'app_city_select', methods: ['GET'])]
     public function index(EntityManagerInterface $em, Request $request) : Response 
     {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // La casse est normalisée en UTF-8 afin que les villes accentuées soient
        // retrouvées de la même façon depuis toutes les langues de l’interface.
        $search = trim($request->query->getString('query'));
        $normalizedSearch = mb_strtolower($search);

        $queryBuilder = $em->getRepository(City::class)->createQueryBuilder('c')
            ->where('c.available = true')
            ->orderBy('c.name', 'ASC');

        if ($normalizedSearch !== '') {
            $queryBuilder->andWhere('Lower(c.name) LIKE :search')
                ->setParameter('search', '%' . $normalizedSearch . '%');
        }

        $cities = $queryBuilder->getQuery()->getResult();

        return $this->render('city/index.html.twig', [
            'cities' => $cities,
            'search' => $search,
        ]);
     }
     #[Route('/choose/{id}', name: 'app_city_choose', methods: ['POST'])]
     public function choose(
        City $city,
        EntityManagerInterface $em,
        Request $request,
        TranslatorInterface $translator,
     ): Response
     {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$city->isAvailable()) {
            throw $this->createNotFoundException($translator->trans('security.city_unavailable'));
        }

        if(!$this->isCsrfTokenValid('choose_city', $request->request->get('_token'))){
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setCity($city);
        $em->flush();

        $this->addFlash('success', $translator->trans(
            'flash.city_updated',
            ['%city%' => $city->getName()],
        ));

        return $this->redirectToRoute('app_city_select');
     }
}


