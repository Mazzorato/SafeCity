<?php

namespace App\Service;

/**
 * Extrait du GTFS Tisséo uniquement les métros, les trams et le Linéo L1.
 *
 * Le lecteur s'appuie sur PharData, déjà fourni par PHP, afin de ne pas ajouter
 * de dépendance ni d'imposer l'extension zip à l'installation locale.
 */
final class TisseoGtfsReader
{
    private const REQUIRED_FILES = [
        'routes.txt',
        'trips.txt',
        'stops.txt',
        'stop_times.txt',
        'calendar.txt',
        'calendar_dates.txt',
    ];

    /**
     * @return array{
     *     lines: array<string, array{id: string, line: string, name: string, type: string}>,
     *     stops: array<string, array{id: string, routeId: string, line: string, name: string, stopIds: list<string>}>,
     *     departures: array<string, list<array{tripId: string, time: int, headsign: string}>>
     * }
     */
    public function read(string $archiveContent, ?\DateTimeImmutable $now = null): array
    {
        if ($archiveContent === '') {
            throw new \UnexpectedValueException('L’archive GTFS Tisséo est vide.');
        }

        $temporaryPath = sprintf(
            '%s%ssafecity-tisseo-%s.zip',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            bin2hex(random_bytes(8)),
        );

        try {
            if (file_put_contents($temporaryPath, $archiveContent) === false) {
                throw new \RuntimeException('Impossible de préparer temporairement le GTFS Tisséo.');
            }

            $archive = new \PharData($temporaryPath);
            $sources = [];

            foreach (self::REQUIRED_FILES as $filename) {
                if (!isset($archive[$filename])) {
                    throw new \UnexpectedValueException(sprintf(
                        'Le fichier GTFS obligatoire « %s » est absent.',
                        $filename,
                    ));
                }

                // openFile() lit le CSV au fil de l'eau. Charger stop_times.txt
                // en entier dépasserait la limite mémoire PHP de l'application.
                $sources[$filename] = $archive[$filename]->openFile();
            }

            return $this->parseSources($sources, $now);
        } finally {
            // Cette copie est exclusivement créée pour lire l'archive distante
            // et ne doit pas rester sur l'ordinateur après son extraction.
            unset($sources, $archive);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Point d'entrée séparé pour tester le contenu GTFS sans accès réseau.
     *
     * @param array<string, string> $files
     *
     * @return array{
     *     lines: array<string, array{id: string, line: string, name: string, type: string}>,
     *     stops: array<string, array{id: string, routeId: string, line: string, name: string, stopIds: list<string>}>,
     *     departures: array<string, list<array{tripId: string, time: int, headsign: string}>>
     * }
     */
    public function parseFiles(array $files, ?\DateTimeImmutable $now = null): array
    {
        foreach (self::REQUIRED_FILES as $filename) {
            if (!isset($files[$filename])) {
                throw new \UnexpectedValueException(sprintf(
                    'Le fichier GTFS obligatoire « %s » est absent.',
                    $filename,
                ));
            }
        }

        return $this->parseSources($files, $now);
    }

    /**
     * @param array<string, string|\SplFileObject> $sources
     *
     * @return array{
     *     lines: array<string, array{id: string, line: string, name: string, type: string}>,
     *     stops: array<string, array{id: string, routeId: string, line: string, name: string, stopIds: list<string>}>,
     *     departures: array<string, list<array{tripId: string, time: int, headsign: string}>>
     * }
     */
    private function parseSources(array $sources, ?\DateTimeImmutable $now): array
    {
        $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
            ->setTimezone(new \DateTimeZone('Europe/Paris'));
        $lines = $this->readSelectedLines($sources['routes.txt']);
        $calendar = $this->readCalendar($sources['calendar.txt']);
        $exceptions = $this->readCalendarExceptions($sources['calendar_dates.txt']);
        $activeServices = $this->activeServiceDates($calendar, $exceptions, $now);
        $trips = $this->readTrips($sources['trips.txt'], $lines, $activeServices);
        [$departures, $routeStopIds] = $this->readStopTimes($sources['stop_times.txt'], $trips, $now);

        // Les voyages et calendriers ne servent plus après le calcul des
        // prochains passages et peuvent être libérés avant la lecture des arrêts.
        unset($trips, $calendar, $exceptions, $activeServices);
        $stops = $this->readStops($sources['stops.txt'], $lines, $routeStopIds);

        return [
            'lines' => $lines,
            'stops' => $stops,
            'departures' => $departures,
        ];
    }

    /**
     * @return array<string, array{id: string, line: string, name: string, type: string}>
     */
    private function readSelectedLines(string|\SplFileObject $content): array
    {
        $lines = [];

        foreach ($this->csvRows($content) as $row) {
            $shortName = trim($row['route_short_name'] ?? '');
            $routeType = trim($row['route_type'] ?? '');
            $type = match (true) {
                $routeType === '1' => 'metro',
                $routeType === '0' => 'tram',
                $routeType === '3' && mb_strtoupper($shortName) === 'L1' => 'bus',
                default => null,
            };

            if ($type === null) {
                continue;
            }

            $routeId = trim($row['route_id'] ?? '');
            if ($routeId === '') {
                continue;
            }

            $lines[$routeId] = [
                'id' => $routeId,
                'line' => $shortName,
                'name' => trim($row['route_long_name'] ?? '') ?: $this->fallbackLineName($type, $shortName),
                'type' => $type,
            ];
        }

        uasort($lines, static function (array $left, array $right): int {
            $typeOrder = ['metro' => 0, 'tram' => 1, 'bus' => 2];

            return [$typeOrder[$left['type']], $left['line']]
                <=> [$typeOrder[$right['type']], $right['line']];
        });

        if ($lines === []) {
            throw new \UnexpectedValueException('Aucune ligne Tisséo autorisée n’a été trouvée.');
        }

        return $lines;
    }

    /**
     * @param array<string, array{id: string, line: string, name: string, type: string}> $lines
     * @param array<string, list<int>>                                                       $activeServices
     *
     * @return array<string, array{routeId: string, headsign: string, serviceDates: list<int>}>
     */
    private function readTrips(
        string|\SplFileObject $content,
        array $lines,
        array $activeServices,
    ): array
    {
        $trips = [];

        foreach ($this->csvRows($content) as $row) {
            $routeId = trim($row['route_id'] ?? '');
            $tripId = trim($row['trip_id'] ?? '');
            $serviceId = trim($row['service_id'] ?? '');

            if (
                !isset($lines[$routeId])
                || !isset($activeServices[$serviceId])
                || $tripId === ''
            ) {
                continue;
            }

            $trips[$tripId] = [
                'routeId' => $routeId,
                'headsign' => trim($row['trip_headsign'] ?? ''),
                'serviceDates' => $activeServices[$serviceId],
            ];
        }

        return $trips;
    }

    /**
     * @param array<string, array{routeId: string, headsign: string, serviceDates: list<int>}> $trips
     *
     * @return array{
     *     array<string, list<array{tripId: string, time: int, headsign: string}>>,
     *     array<string, array<string, true>>
     * }
     */
    private function readStopTimes(
        string|\SplFileObject $content,
        array $trips,
        \DateTimeImmutable $now,
    ): array
    {
        $departures = [];
        $routeStopIds = [];
        $firstAcceptedTime = $now->modify('-1 hour')->getTimestamp();
        $lastAcceptedTime = $now->modify('+7 hours')->getTimestamp();

        foreach ($this->csvRows($content) as $row) {
            $tripId = trim($row['trip_id'] ?? '');
            $stopId = trim($row['stop_id'] ?? '');
            $departure = trim($row['departure_time'] ?? '');

            if (!isset($trips[$tripId]) || $stopId === '' || !$this->isGtfsTime($departure)) {
                continue;
            }

            $trip = $trips[$tripId];
            $routeStopIds[$trip['routeId']][$stopId] = true;
            $departureSeconds = $this->gtfsTimeToSeconds($departure);

            foreach ($trip['serviceDates'] as $serviceDate) {
                $timestamp = $serviceDate + $departureSeconds;
                if ($timestamp < $firstAcceptedTime || $timestamp > $lastAcceptedTime) {
                    continue;
                }

                $departures[$stopId][] = [
                    'tripId' => $tripId,
                    'time' => $timestamp,
                    'headsign' => $trip['headsign'],
                ];
            }
        }

        return [$departures, $routeStopIds];
    }

    /**
     * @param array<string, array{id: string, line: string, name: string, type: string}> $lines
     * @param array<string, array<string, true>>                                      $routeStopIds
     *
     * @return array<string, array{id: string, routeId: string, line: string, name: string, stopIds: list<string>}>
     */
    private function readStops(
        string|\SplFileObject $content,
        array $lines,
        array $routeStopIds,
    ): array
    {
        $stopNames = [];
        $selectedStopIds = [];
        foreach ($routeStopIds as $ids) {
            $selectedStopIds += $ids;
        }

        foreach ($this->csvRows($content) as $row) {
            $stopId = trim($row['stop_id'] ?? '');
            $stopName = trim($row['stop_name'] ?? '');

            if (isset($selectedStopIds[$stopId]) && $stopName !== '') {
                $stopNames[$stopId] = $stopName;
            }
        }

        $stops = [];
        foreach ($routeStopIds as $routeId => $ids) {
            $groupedStops = [];

            foreach (array_keys($ids) as $stopId) {
                if (!isset($stopNames[$stopId])) {
                    continue;
                }

                $normalizedName = mb_strtolower($stopNames[$stopId]);
                $groupedStops[$normalizedName] ??= [
                    'name' => $stopNames[$stopId],
                    'stopIds' => [],
                ];
                $groupedStops[$normalizedName]['stopIds'][] = $stopId;
            }

            foreach ($groupedStops as $stop) {
                $selectionId = substr(hash('sha256', $routeId . "\0" . mb_strtolower($stop['name'])), 0, 24);
                $stops[$selectionId] = [
                    'id' => $selectionId,
                    'routeId' => $routeId,
                    'line' => $lines[$routeId]['line'],
                    'name' => $stop['name'],
                    'stopIds' => array_values(array_unique($stop['stopIds'])),
                ];
            }
        }

        uasort($stops, static fn (array $left, array $right): int => strnatcasecmp(
            $left['line'] . ' ' . $left['name'],
            $right['line'] . ' ' . $right['name'],
        ));

        return $stops;
    }

    /**
     * @return array<string, array{startDate: string, endDate: string, weekdays: array<string, bool>}>
     */
    private function readCalendar(string|\SplFileObject $content): array
    {
        $calendar = [];
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($this->csvRows($content) as $row) {
            $serviceId = trim($row['service_id'] ?? '');
            if ($serviceId === '') {
                continue;
            }

            $activeWeekdays = [];
            foreach ($weekdays as $weekday) {
                $activeWeekdays[$weekday] = ($row[$weekday] ?? '0') === '1';
            }

            $calendar[$serviceId] = [
                'startDate' => trim($row['start_date'] ?? ''),
                'endDate' => trim($row['end_date'] ?? ''),
                'weekdays' => $activeWeekdays,
            ];
        }

        return $calendar;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function readCalendarExceptions(string|\SplFileObject $content): array
    {
        $exceptions = [];

        foreach ($this->csvRows($content) as $row) {
            $date = trim($row['date'] ?? '');
            $serviceId = trim($row['service_id'] ?? '');
            $exceptionType = (int) ($row['exception_type'] ?? 0);

            if ($date !== '' && $serviceId !== '' && in_array($exceptionType, [1, 2], true)) {
                $exceptions[$date][$serviceId] = $exceptionType;
            }
        }

        return $exceptions;
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function csvRows(string|\SplFileObject $content): \Generator
    {
        if ($content instanceof \SplFileObject) {
            $content->rewind();
            $headers = $content->fgetcsv(',', '"', '');

            if (!is_array($headers)) {
                return;
            }

            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");

            while (!$content->eof()) {
                $values = $content->fgetcsv(',', '"', '');
                $row = $this->combineCsvRow($headers, $values);

                if ($row !== null) {
                    yield $row;
                }
            }

            return;
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Impossible de préparer la lecture du fichier GTFS.');
        }

        try {
            fwrite($stream, $content);
            rewind($stream);
            $headers = fgetcsv($stream, null, ',', '"', '');

            if (!is_array($headers)) {
                return;
            }

            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $row = $this->combineCsvRow($headers, $values);

                if ($row !== null) {
                    yield $row;
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function isGtfsTime(string $time): bool
    {
        return preg_match('/^\d{1,2}:[0-5]\d:[0-5]\d$/', $time) === 1;
    }

    /**
     * @param list<mixed>       $headers
     * @param list<mixed>|false $values
     *
     * @return array<string, string>|null
     */
    private function combineCsvRow(array $headers, array|false $values): ?array
    {
        if ($values === false || $values === [null] || count($values) !== count($headers)) {
            return null;
        }

        /** @var array<string, string> $row */
        $row = array_combine($headers, array_map(
            static fn (mixed $value): string => (string) $value,
            $values,
        ));

        return $row;
    }

    /**
     * @param array<string, array{startDate: string, endDate: string, weekdays: array<string, bool>}> $calendar
     * @param array<string, array<string, int>>                                                       $exceptions
     *
     * @return array<string, list<int>>
     */
    private function activeServiceDates(
        array $calendar,
        array $exceptions,
        \DateTimeImmutable $now,
    ): array {
        $activeServices = [];
        $dates = [
            $now->modify('-1 day')->setTime(0, 0),
            $now->setTime(0, 0),
            $now->modify('+1 day')->setTime(0, 0),
        ];

        foreach ($dates as $date) {
            $formattedDate = $date->format('Ymd');
            foreach ($calendar as $serviceId => $service) {
                $exception = $exceptions[$formattedDate][$serviceId] ?? null;
                $active = $exception === 1 || (
                    $exception === null
                    && $formattedDate >= $service['startDate']
                    && $formattedDate <= $service['endDate']
                    && ($service['weekdays'][mb_strtolower($date->format('l'))] ?? false)
                );

                if ($active) {
                    $activeServices[$serviceId][] = $date->getTimestamp();
                }
            }

            // Un service peut être ajouté uniquement par calendar_dates.
            foreach ($exceptions[$formattedDate] ?? [] as $serviceId => $exceptionType) {
                if ($exceptionType === 1 && !isset($calendar[$serviceId])) {
                    $activeServices[$serviceId][] = $date->getTimestamp();
                }
            }
        }

        return $activeServices;
    }

    private function gtfsTimeToSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function fallbackLineName(string $type, string $shortName): string
    {
        return match ($type) {
            'metro' => sprintf('Métro %s', $shortName),
            'tram' => sprintf('Tram %s', $shortName),
            default => sprintf('Linéo %s', ltrim($shortName, 'L')),
        };
    }
}


