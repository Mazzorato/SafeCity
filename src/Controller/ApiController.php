<?php

namespace App\Controller;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\LocalService;
use App\Entity\News;
use App\Entity\Parking;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportStatusEnum;
use App\Service\TisseoOpenDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'app_api_')]
/**
 * Expose les données publiques de SafeCity sous forme de réponses JSON.
 */
final class ApiController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->json([
            'name' => 'SafeCity API',
            'version' => '1.0',
            'scope' => 'Données en lecture seule pour la ville de l’utilisateur connecté.',
            'endpoints' => [
                'reports' => $this->generateUrl('app_api_reports'),
                'mobility' => $this->generateUrl('app_api_mobility'),
                'services' => $this->generateUrl('app_api_services'),
                'events' => $this->generateUrl('app_api_events'),
                'news' => $this->generateUrl('app_api_news'),
            ],
        ]);
    }

    #[Route('/reports', name: 'reports', methods: ['GET'])]
    public function reports(
        EntityManagerInterface $entityManager,
        Request $request,
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $city = $this->currentCity();
        $status = $request->query->getString('status', 'all');
        $category = trim(mb_strtolower($request->query->getString('category', 'all')));
        $excludeResolved = $request->query->getBoolean('excludeResolved');
        $categoryGroups = [
            'accidents' => ['accident', 'route', 'voiture'],
            'travaux' => ['travaux', 'chantier'],
            'urgences' => ['incendie', 'sante', 'santé', 'urgence'],
        ];
        $allowedStatuses = ['all', ...array_map(
            static fn (ReportStatusEnum $reportStatus): string => $reportStatus->value,
            ReportStatusEnum::cases()
        )];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $reports = $city === null
            ? []
            : $entityManager->getRepository(Report::class)->findBy(['city' => $city], ['createdAt' => 'DESC']);

        $reports = array_values(array_filter(
            $reports,
            static function (Report $report) use ($status, $category, $categoryGroups, $excludeResolved): bool {
                // La carte peut masquer les dossiers terminés sans retirer leur
                // accès explicite aux autres consommateurs de l’API interne.
                if ($excludeResolved && $report->getStatus() === ReportStatusEnum::RESOLVED) {
                    return false;
                }
                if ($status !== 'all' && $report->getStatus()?->value !== $status) {
                    return false;
                }
                if ($category === 'all') {
                    return true;
                }

                $icon = mb_strtolower((string) $report->getCategory()?->getIcon());

                return isset($categoryGroups[$category])
                    ? in_array($icon, $categoryGroups[$category], true)
                    : $icon === $category;
            }
        ));

        return $this->cityResponse($city, array_map(
            static fn (Report $report): array => [
                'id' => $report->getId(),
                'category' => [
                    'name' => $report->getCategory()?->getName(),
                    'icon' => $report->getCategory()?->getIcon(),
                ],
                'description' => $report->getDescription(),
                'gravity' => $report->getGravityLevel()?->value,
                'status' => $report->getStatus()?->value,
                'location' => [
                    'address' => $report->getAddress(),
                    'latitude' => $report->getLatitude(),
                    'longitude' => $report->getLongitude(),
                ],
                'createdAt' => $report->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'updatedAt' => $report->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                'commentsCount' => $report->getComments()->count(),
                'photosCount' => $report->getPhotos()->count(),
            ],
            $reports
        ));
    }

    #[Route('/mobility', name: 'mobility', methods: ['GET'])]
    public function mobility(
        EntityManagerInterface $entityManager,
        TisseoOpenDataClient $tisseoOpenData,
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $city = $this->currentCity();
        $network = $tisseoOpenData->getNetwork();
        $parkings = $city === null
            ? []
            : $entityManager->getRepository(Parking::class)->findBy(['city' => $city], ['availableSpots' => 'DESC']);

        return $this->json([
            'city' => $this->cityData($city),
            'transportSource' => [
                'provider' => 'Tisséo Open Data',
                'available' => $network['available'],
                'realtimeAvailable' => $network['realtimeAvailable'],
                'updatedAt' => $network['updatedAt']?->format(\DateTimeInterface::ATOM),
            ],
            'transports' => array_map(
                static fn (array $transport): array => [
                    'id' => $transport['id'],
                    'name' => $transport['name'],
                    'line' => $transport['line'],
                    'type' => $transport['type'],
                    'status' => $transport['status'],
                    'disruption' => $transport['disruption'],
                    'updatedAt' => $transport['updatedAt']->format(\DateTimeInterface::ATOM),
                ],
                $network['lines']
            ),
            'parkings' => array_map(
                static fn (Parking $parking): array => [
                    'id' => $parking->getId(),
                    'name' => $parking->getName(),
                    'address' => $parking->getAddress(),
                    'latitude' => $parking->getLatitude(),
                    'longitude' => $parking->getLongitude(),
                    'free' => $parking->isFree(),
                    // Le montant décimal reste une chaîne JSON pour préserver
                    // exactement les centimes enregistrés par Doctrine.
                    'hourlyRate' => $parking->getHourlyRate(),
                    'availableSpots' => $parking->getAvailableSpots(),
                    'totalSpots' => $parking->getTotalSpots(),
                ],
                $parkings
            ),
        ]);
    }

    #[Route('/services', name: 'services', methods: ['GET'])]
    public function services(EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $city = $this->currentCity();
        $services = $city === null
            ? []
            : $entityManager->getRepository(LocalService::class)->findBy(['city' => $city], ['name' => 'ASC']);

        return $this->cityResponse($city, array_map(
            static fn (LocalService $service): array => [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'type' => $service->getType()?->value,
                'address' => $service->getAddress(),
                'latitude' => $service->getLatitude(),
                'longitude' => $service->getLongitude(),
                'phone' => $service->getPhone(),
                'onDuty' => $service->isOnDuty(),
                'openingHours' => $service->getOpeningHours(),
            ],
            $services
        ));
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $city = $this->currentCity();
        $events = $city === null
            ? []
            : $entityManager->getRepository(Event::class)->findBy(['city' => $city], ['startedAt' => 'ASC']);

        return $this->cityResponse($city, array_map(
            static fn (Event $event): array => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'category' => $event->getCategory()?->value,
                'location' => $event->getLocation(),
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
                'startsAt' => $event->getStartedAt()?->format(\DateTimeInterface::ATOM),
                'endsAt' => $event->getEndedAt()?->format(\DateTimeInterface::ATOM),
                'free' => $event->isFree(),
                'imageUrl' => $event->getImageUrl(),
            ],
            $events
        ));
    }

    #[Route('/news', name: 'news', methods: ['GET'])]
    public function news(EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $city = $this->currentCity();
        $news = $city === null
            ? []
            : $entityManager->getRepository(News::class)->findBy(['city' => $city], ['publishedAt' => 'DESC']);

        return $this->cityResponse($city, array_map(
            static fn (News $item): array => [
                'id' => $item->getId(),
                'title' => $item->getTitle(),
                'content' => $item->getContent(),
                'source' => $item->getSource(),
                'category' => $item->getCategory()?->value,
                'publishedAt' => $item->getPublishedAt()?->format(\DateTimeInterface::ATOM),
                'latitude' => $item->getLatitude(),
                'longitude' => $item->getLongitude(),
                'imageUrl' => $item->getImageUrl(),
                'featured' => $item->isFeatured(),
            ],
            $news
        ));
    }

    private function currentCity(): ?City
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getCity();
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function cityResponse(?City $city, array $data): JsonResponse
    {
        return $this->json([
            'city' => $this->cityData($city),
            'count' => count($data),
            'data' => $data,
        ]);
    }

    /**
     * @return array{id: int|null, name: string|null, latitude: string|null, longitude: string|null}|null
     */
    private function cityData(?City $city): ?array
    {
        if ($city === null) {
            return null;
        }

        return [
            'id' => $city->getId(),
            'name' => $city->getName(),
            'latitude' => $city->getLatitude(),
            'longitude' => $city->getLongitude(),
        ];
    }
}


