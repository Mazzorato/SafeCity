<?php

namespace App\Controller;

use App\Entity\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TransportController extends AbstractController
{
    #[Route('/transport', name: 'app_transport')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $transport = [];
        $disrupted = [];

        if ($city){
            $transports = $em->getRepository(Transport::class)->findBy(['city'=> $city]);

            foreach ($transports as $transport) {
                if ($transport->getDisruption() !== null){
                    $disrupted [] = $transport;
                }
            }
        }
        return $this->render('transport/index.html.twig', [
            'transport' => $transport,
            'city' =>  $city,
            'disrupted' => $disrupted,
        ]);
    }
}
