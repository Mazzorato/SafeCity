<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fournit à SafeCity un sous-ensemble gratuit du réseau Open Data Tisséo.
 *
 * Aucune information utilisateur n'est envoyée : seules les archives et le
 * flux public de Tisséo sont téléchargés par le serveur Symfony.
 */
final class TisseoOpenDataClient
{
    private const GTFS_URL = 'https://data.toulouse-metropole.fr/api/explore/v2.1/catalog/datasets/tisseo-gtfs/alternative_exports/utf_8tisseo_gtfs_v2_zip';
    private const REALTIME_URL = 'https://api.tisseo.fr/opendata/gtfsrt/GtfsRt.json';
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly TisseoGtfsReader $gtfsReader,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *     available: bool,
     *     realtimeAvailable: bool,
     *     lines: list<array{id: string, line: string, name: string, type: string, status: string, disruption: ?string, updatedAt: \DateTimeImmutable}>,
     *     stops: list<array{id: string, routeId: string, line: string, name: string, stopIds: list<string>}>,
     *     selectedStop: ?array{id: string, routeId: string, line: string, name: string, stopIds: list<string>},
     *     departures: list<array{time: \DateTimeImmutable, headsign: string, realtime: bool}>,
     *     updatedAt: ?\DateTimeImmutable
     * }
     */
    public function getNetwork(?string $selectedStopId = null, ?\DateTimeImmutable $now = null): array
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $now = ($now ?? new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);

        try {
            /** @var array{content: string, fetchedAt: int} $archive */
            $archive = $this->cache->get('safecity.tisseo.gtfs.archive.v1', function (ItemInterface $item): array {
                $item->expiresAfter(21600);
                $response = $this->httpClient->request('GET', self::GTFS_URL, [
                    'headers' => [
                        'Accept' => 'application/zip',
                        'User-Agent' => 'SafeCity local Symfony application',
                    ],
                    'timeout' => 15,
                    'max_duration' => 30,
                ]);

                return [
                    'content' => $response->getContent(),
                    'fetchedAt' => time(),
                ];
            });

            // Les horaires conservés couvrent une fenêtre glissante. Ils sont
            // recalculés toutes les dix minutes à partir de l'archive quotidienne.
            $windowKey = 'safecity.tisseo.gtfs.window.v2.' . intdiv($now->getTimestamp(), 600);
            /** @var array{dataset: array<string, mixed>, fetchedAt: int} $static */
            $static = $this->cache->get($windowKey, function (ItemInterface $item) use ($archive, $now): array {
                $item->expiresAfter(660);

                return [
                    'dataset' => $this->gtfsReader->read($archive['content'], $now),
                    'fetchedAt' => $archive['fetchedAt'],
                ];
            });
        } catch (\Throwable $exception) {
            $this->logger->warning('Les données statiques Tisséo sont indisponibles.', [
                'exception' => $exception,
            ]);

            return $this->unavailableNetwork();
        }

        $realtimeAvailable = true;
        try {
            /** @var array{alerts: array<string, list<array{title: string, url: ?string}>>, updates: array<string, int>, timestamp: ?int} $realtime */
            $realtime = $this->cache->get('safecity.tisseo.realtime.v1', function (ItemInterface $item): array {
                // Le flux évolue toutes les cinq secondes ; trente secondes
                // évitent de le surcharger tout en gardant une page actuelle.
                $item->expiresAfter(30);
                $response = $this->httpClient->request('GET', self::REALTIME_URL, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'User-Agent' => 'SafeCity local Symfony application',
                    ],
                    'timeout' => 8,
                    'max_duration' => 12,
                ]);
                $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($payload)) {
                    throw new \UnexpectedValueException('Le flux temps réel Tisséo est invalide.');
                }

                return $this->normalizeRealtime($payload);
            });
        } catch (\Throwable $exception) {
            $realtimeAvailable = false;
            $realtime = ['alerts' => [], 'updates' => [], 'timestamp' => null];
            $this->logger->warning('Le temps réel Tisséo est indisponible.', [
                'exception' => $exception,
            ]);
        }

        /** @var array{
         *     lines: array<string, array{id: string, line: string, name: string, type: string}>,
         *     stops: array<string, array{id: string, routeId: string, line: string, name: string, stopIds: list<string>}>,
         *     departures: array<string, list<array{tripId: string, time: int, headsign: string}>>
         * } $dataset
         */
        $dataset = $static['dataset'];
        $updatedTimestamp = $realtime['timestamp'] ?? $static['fetchedAt'];
        $updatedAt = (new \DateTimeImmutable('@' . $updatedTimestamp))->setTimezone($timezone);
        $lines = [];

        foreach ($dataset['lines'] as $routeId => $line) {
            $alerts = $realtime['alerts'][$routeId] ?? [];
            $disruption = $alerts === []
                ? null
                : implode(' · ', array_slice(array_column($alerts, 'title'), 0, 2));

            $lines[] = [
                ...$line,
                'status' => !$realtimeAvailable ? 'unknown' : ($disruption === null ? 'ok' : 'disrupted'),
                'disruption' => $disruption,
                'updatedAt' => $updatedAt,
            ];
        }

        $selectedStop = $selectedStopId !== null && isset($dataset['stops'][$selectedStopId])
            ? $dataset['stops'][$selectedStopId]
            : null;

        return [
            'available' => true,
            'realtimeAvailable' => $realtimeAvailable,
            'lines' => $lines,
            'stops' => array_values($dataset['stops']),
            'selectedStop' => $selectedStop,
            'departures' => $selectedStop === null
                ? []
                : $this->findDepartures($dataset, $selectedStop, $realtime['updates'], $now),
            'updatedAt' => $updatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     alerts: array<string, list<array{title: string, url: ?string}>>,
     *     updates: array<string, int>,
     *     timestamp: ?int
     * }
     */
    private function normalizeRealtime(array $payload): array
    {
        $alerts = [];
        $updates = [];

        foreach ($payload['entity'] ?? [] as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            $alert = $entity['alert'] ?? null;
            if (is_array($alert)) {
                $title = $this->translatedText($alert['header_text']['translation'] ?? []);
                $url = $this->translatedText($alert['url']['translation'] ?? []);

                if ($title !== null) {
                    foreach ($alert['informed_entity'] ?? [] as $informedEntity) {
                        $routeId = is_array($informedEntity)
                            ? ($informedEntity['route_id'] ?? null)
                            : null;

                        if (is_string($routeId) && $routeId !== '') {
                            $alerts[$routeId][] = [
                                'title' => trim(strip_tags(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                                'url' => $url,
                            ];
                        }
                    }
                }
            }

            $tripUpdate = $entity['trip_update'] ?? null;
            if (!is_array($tripUpdate)) {
                continue;
            }

            $tripId = $tripUpdate['trip']['trip_id'] ?? null;
            if (!is_string($tripId) || $tripId === '') {
                continue;
            }

            foreach ($tripUpdate['stop_time_update'] ?? [] as $stopUpdate) {
                if (!is_array($stopUpdate)) {
                    continue;
                }

                $stopId = $stopUpdate['stop_id'] ?? null;
                $timestamp = $stopUpdate['departure']['time']
                    ?? $stopUpdate['arrival']['time']
                    ?? null;

                if (is_string($stopId) && $stopId !== '' && is_numeric($timestamp)) {
                    $updates[$tripId . "\0" . $stopId] = (int) $timestamp;
                }
            }
        }

        $timestamp = $payload['header']['timestamp'] ?? null;

        return [
            'alerts' => $alerts,
            'updates' => $updates,
            'timestamp' => is_numeric($timestamp) ? (int) $timestamp : null,
        ];
    }

    /**
     * @param array{
     *     departures: array<string, list<array{tripId: string, time: int, headsign: string}>>
     * }                                                                                          $dataset
     * @param array{id: string, routeId: string, line: string, name: string, stopIds: list<string>} $stop
     * @param array<string, int>                                                                $realtimeUpdates
     *
     * @return list<array{time: \DateTimeImmutable, headsign: string, realtime: bool}>
     */
    private function findDepartures(
        array $dataset,
        array $stop,
        array $realtimeUpdates,
        \DateTimeImmutable $now,
    ): array {
        $departures = [];
        $lastAcceptedTime = $now->modify('+6 hours')->getTimestamp();

        foreach ($stop['stopIds'] as $stopId) {
            foreach ($dataset['departures'][$stopId] ?? [] as $departure) {
                $scheduledTimestamp = $departure['time'];
                $realtimeCandidate = $realtimeUpdates[$departure['tripId'] . "\0" . $stopId] ?? null;
                // Un identifiant de trajet peut être réutilisé plusieurs
                // jours : une mise à jour ne vaut que pour le passage proche.
                $realtimeTimestamp = $realtimeCandidate !== null
                    && abs($realtimeCandidate - $scheduledTimestamp) <= 43200
                        ? $realtimeCandidate
                        : null;
                $effectiveTimestamp = $realtimeTimestamp ?? $scheduledTimestamp;

                if ($effectiveTimestamp < $now->getTimestamp() || $effectiveTimestamp > $lastAcceptedTime) {
                    continue;
                }

                $deduplicationKey = $effectiveTimestamp . "\0" . $departure['headsign'];
                $departures[$deduplicationKey] = [
                    'time' => (new \DateTimeImmutable('@' . $effectiveTimestamp))
                        ->setTimezone($now->getTimezone()),
                    'headsign' => $departure['headsign'],
                    'realtime' => $realtimeTimestamp !== null,
                ];
            }
        }

        uasort(
            $departures,
            static fn (array $left, array $right): int => $left['time'] <=> $right['time'],
        );

        return array_slice(array_values($departures), 0, 8);
    }

    /**
     * @param mixed $translations
     */
    private function translatedText(mixed $translations): ?string
    {
        if (!is_array($translations)) {
            return null;
        }

        foreach ($translations as $translation) {
            if (is_array($translation) && ($translation['language'] ?? null) === 'fr') {
                return is_string($translation['text'] ?? null) ? $translation['text'] : null;
            }
        }

        $first = $translations[0]['text'] ?? null;

        return is_string($first) && $first !== '' ? $first : null;
    }

    /**
     * @return array{
     *     available: false,
     *     realtimeAvailable: false,
     *     lines: [],
     *     stops: [],
     *     selectedStop: null,
     *     departures: [],
     *     updatedAt: null
     * }
     */
    private function unavailableNetwork(): array
    {
        return [
            'available' => false,
            'realtimeAvailable' => false,
            'lines' => [],
            'stops' => [],
            'selectedStop' => null,
            'departures' => [],
            'updatedAt' => null,
        ];
    }
}


