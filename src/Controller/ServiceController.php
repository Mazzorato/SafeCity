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

#[Route('/service/health/{kind}', name: 'app_service_health', methods: ['GET'], requirements: ['kind' => 'doctor|pharmacy'])]
    public function health(EntityManagerInterface $entityManager, Request $request, string $kind): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $search = trim($request->query->getString('query'));
        $filter = $request->query->getString('filter', 'all');
        $locationAllowed = $user->getProfile()?->isLocationAccess() === true;
        $queryParameters = $request->query->all();
        $latitude = $locationAllowed
            ? $this->parseCoordinate($queryParameters['latitude'] ?? null, -90.0, 90.0)
            : null;
        $longitude = $locationAllowed
            ? $this->parseCoordinate($queryParameters['longitude'] ?? null, -180.0, 180.0)
            : null;
        if ($latitude === null || $longitude === null) {
            $latitude = null;
            $longitude = null;
        }
        $filter = 'all';
        $services = [];
        if ($city !== null) {
            $queryBuilder = $entityManager->getRepository(LocalService::class)->createQueryBuilder('service')
                ->where('service.city = :city')
                ->andWhere('service.type = :health')
                ->setParameter('city', $city)
                ->setParameter('health', ServiceTypeEnum::HEALTH)
                ->orderBy('service.onDuty', 'DESC')
                ->addOrderBy('service.name', 'ASC');

            $kind === 'pharmacy'
                ? $queryBuilder->andWhere('LOWER(service.name) LIKE :pharmacy')->setParameter('pharmacy', '%pharmacie%')
                : $queryBuilder->andWhere('LOWER(service.name) NOT LIKE :pharmacy')->setParameter('pharmacy', '%pharmacie%');

            if ($search !== '') {
                $queryBuilder
                    ->andWhere('LOWER(service.name) LIKE :search OR LOWER(service.address) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            $services = $queryBuilder->getQuery()->getResult();
        }

        $serviceDistances = [];
        if ($latitude !== null && $longitude !== null) {
            foreach ($services as $service) {
                $serviceDistances[$service->getId()] = $this->distanceInKilometers(
                    $latitude,
                    $longitude,
                    (float) $service->getLatitude(),
                    (float) $service->getLongitude(),
                );
            }
            usort($services, static function (LocalService $first, LocalService $second) use ($serviceDistances): int {
                $comparison = $serviceDistances[$first->getId()] <=> $serviceDistances[$second->getId()];

                return $comparison !== 0
                    ? $comparison
                    : strcasecmp((string) $first->getName(), (string) $second->getName());
            });
        }
        $mapServices = array_map(
            static fn (LocalService $service): array => [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'address' => $service->getAddress(),
                'latitude' => (float) $service->getLatitude(),
                'longitude' => (float) $service->getLongitude(),
                'onDuty' => $service->isOnDuty(),
                'distance' => $serviceDistances[$service->getId()] ?? null,
            ],
            $services,
        );

        return $this->render('service/health.html.twig', [
            'services' => $services,
            'serviceDistances' => $serviceDistances,
            'nearbyAlternatives' => [],
            'mapServices' => $mapServices,
            'kind' => $kind,
            'filter' => $filter,
            'search' => $search,
            'city' => $city,
            'locationAllowed' => $locationAllowed,
            'userLatitude' => $latitude,
            'userLongitude' => $longitude,
        ]);
    }


private function parseCoordinate(mixed $value, float $minimum, float $maximum): ?float
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $normalizedValue = str_replace(',', '.', trim((string) $value));
        if ($normalizedValue === '' || !is_numeric($normalizedValue)) {
            return null;
        }

        $coordinate = (float) $normalizedValue;

        return is_finite($coordinate) && $coordinate >= $minimum && $coordinate <= $maximum
            ? $coordinate
            : null;
    }

private function distanceInKilometers(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB,
    ): float {
        $earthRadius = 6371.0;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA))
            * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;
        $haversine = min(1.0, max(0.0, $haversine));

        return $earthRadius * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}


