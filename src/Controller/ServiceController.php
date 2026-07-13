<?php

namespace App\Controller;

use App\Entity\LocalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceController extends AbstractController
{
    #[Route('/service', name: 'app_service')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $servicesByType= [];
        $cityHall = null;

        if ($city) {
            $services = $em->getRepository(LocalService::class)->findBy(['city' => $city]);
            
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
        ]);
    }
}
