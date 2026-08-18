<?php

namespace App\Controller;

use App\Entity\Parking;
use App\Entity\User;
use App\Service\TisseoOpenDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Présente les transports et les informations de mobilité.
 */
final class TransportController extends AbstractController
{
    #[Route('/mobility', name: 'app_mobility', methods: ['GET'])]
    public function mobility(
        EntityManagerInterface $entityManager,
        TisseoOpenDataClient $tisseoOpenData,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $parkings = [];

        if ($city !== null) {
            $parkings = $entityManager->getRepository(Parking::class)->findBy(
                ['city' => $city],
                ['availableSpots' => 'DESC'],
                4
            );
        }

        // Le flux Tisséo ne reçoit aucune information sur l'utilisateur :
        // le serveur télécharge simplement les données publiques du réseau.
        $network = $tisseoOpenData->getNetwork();
        $transports = $network['lines'];

        return $this->render('transport/mobility.html.twig', [
            'city' => $city,
            'transports' => $transports,
            'parkings' => $parkings,
            'disrupted' => array_values(array_filter(
                $transports,
                static fn (array $transport): bool => $transport['status'] === 'disrupted'
            )),
            'transportDataAvailable' => $network['available'],
            'transportRealtimeAvailable' => $network['realtimeAvailable'],
        ]);
    }

    #[Route('/transport', name: 'app_transport', methods: ['GET'])]
    public function index(Request $request, TisseoOpenDataClient $tisseoOpenData): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $type = $request->query->getString('type', 'all');
        $search = trim($request->query->getString('query'));
        $selectedStopId = trim($request->query->getString('schedule'));
        $allowedTypes = ['all', 'disrupted', 'metro', 'tram', 'bus'];

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $network = $tisseoOpenData->getNetwork($selectedStopId !== '' ? $selectedStopId : null);
        $transports = array_values(array_filter(
            $network['lines'],
            static function (array $transport) use ($type, $search): bool {
                if ($type === 'disrupted' && $transport['status'] !== 'disrupted') {
                    return false;
                }
                if (in_array($type, ['metro', 'tram', 'bus'], true) && $transport['type'] !== $type) {
                    return false;
                }
                if ($search === '') {
                    return true;
                }

                $searchableText = mb_strtolower($transport['line'] . ' ' . $transport['name']);

                return str_contains($searchableText, mb_strtolower($search));
            }
        ));
        $disrupted = array_values(array_filter(
            $network['lines'],
            static fn (array $transport): bool => $transport['status'] === 'disrupted'
        ));

        return $this->render('transport/index.html.twig', [
            'transports' => $transports,
            'city' =>  $city,
            'disrupted' => $disrupted,
            'type' => $type,
            'search' => $search,
            'transportDataAvailable' => $network['available'],
            'transportRealtimeAvailable' => $network['realtimeAvailable'],
            'scheduleStops' => $network['stops'],
            'selectedStop' => $network['selectedStop'],
            'departures' => $network['departures'],
        ]);
    }
}


