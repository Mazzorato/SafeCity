<?php

namespace App\Controller;

use App\Entity\Parking;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Présente les informations de stationnement de la ville.
 */
final class ParkingController extends AbstractController
{
    #[Route('/parking', name: 'app_parking')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $queryParameters = $request->query->all();
        $addressValue = $queryParameters['address'] ?? '';
        $address = is_scalar($addressValue)
            ? mb_substr(trim((string) $addressValue), 0, 255)
            : '';
        $typeValue = $queryParameters['type'] ?? 'all';
        $type = is_string($typeValue) ? $typeValue : 'all';
        $sourceValue = $queryParameters['source'] ?? '';
        $source = is_string($sourceValue) ? $sourceValue : '';
        if (!in_array($source, ['address', 'device'], true)) {
            $source = '';
        }

        $locationAllowed = $user->getProfile()?->isLocationAccess() === true;
        $coordinatesAllowed = $source === 'address' || ($source === 'device' && $locationAllowed);
        $latitude = $coordinatesAllowed
            ? $this->parseCoordinate($queryParameters['latitude'] ?? null, -90.0, 90.0)
            : null;
        $longitude = $coordinatesAllowed
            ? $this->parseCoordinate($queryParameters['longitude'] ?? null, -180.0, 180.0)
            : null;

        // Les deux coordonnées doivent être valides pour définir une origine.
        if ($latitude === null || $longitude === null) {
            $latitude = null;
            $longitude = null;
            $source = '';
        }

        if (!in_array($type, ['all', 'free', 'paid', 'available'], true)) {
            $type = 'all';
        }

        $parkings = [];

        if ($city) {
            $queryBuilder = $em->getRepository(Parking::class)->createQueryBuilder('p')
                ->where('p.city = :city')
                ->setParameter('city', $city);

            if ($type === 'free') {
                $queryBuilder->andWhere('p.isFree = true');
            } elseif ($type === 'paid') {
                $queryBuilder->andWhere('p.isFree = false');
            } elseif ($type === 'available') {
                $queryBuilder->andWhere('p.availableSpots > 0');
            }
            $queryBuilder
                ->orderBy('p.availableSpots', 'DESC')
                ->addOrderBy('p.name', 'ASC');
            $parkings = $queryBuilder->getQuery()->getResult();
        }

        $parkingDistances = [];
        if ($latitude !== null && $longitude !== null) {
            foreach ($parkings as $parking) {
                $parkingDistances[$parking->getId()] = $this->distanceInKilometers(
                    $latitude,
                    $longitude,
                    (float) $parking->getLatitude(),
                    (float) $parking->getLongitude(),
                );
            }

            // Une origine valide remplace le classement par disponibilité par
            // un classement géographique stable.
            usort($parkings, static function (Parking $first, Parking $second) use ($parkingDistances): int {
                $distanceComparison = $parkingDistances[$first->getId()] <=> $parkingDistances[$second->getId()];

                return $distanceComparison !== 0
                    ? $distanceComparison
                    : strcasecmp((string) $first->getName(), (string) $second->getName());
            });
        }

        // Ce tableau limite les données exposées au contrôleur cartographique.
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
                'distance' => $parkingDistances[$parking->getId()] ?? null,
            ],
            $parkings,
        );

        // Le séparateur décimal suit la langue active sans dépendance PHP/Twig
        // supplémentaire ni service externe.
        $language = mb_substr(str_replace('-', '_', $request->getLocale()), 0, 2);
        $decimalSeparator = in_array($language, ['en', 'ja'], true) ? '.' : ',';
        $parkingHourlyRates = [];
        foreach ($parkings as $parking) {
            $formattedRate = number_format((float) $parking->getHourlyRate(), 2, $decimalSeparator, '');
            $parkingHourlyRates[$parking->getId()] = in_array($language, ['en', 'ja'], true)
                ? '€' . $formattedRate
                : $formattedRate . ' €';
        }

        return $this->render('parking/index.html.twig', [
            'parkings' => $parkings,
            'parkingDistances' => $parkingDistances,
            'parkingHourlyRates' => $parkingHourlyRates,
            'mapParkings' => $mapParkings,
            'city' => $city,
            'address' => $address,
            'type' => $type,
            'locationAllowed' => $locationAllowed,
            'originSource' => $source,
            'originLatitude' => $latitude,
            'originLongitude' => $longitude,
        ]);
    }

    /**
     * Normalise une coordonnée d'URL sans accepter les tableaux ni les valeurs
     * situées hors des limites géographiques.
     */
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

    /**
     * Calcule localement la distance à vol d'oiseau entre l'origine et un
     * parking, sans API payante ni extension géographique de PostgreSQL.
     */
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


