<?php

namespace App\Tests\Service;

use App\Service\TisseoGtfsReader;
use App\Service\TisseoOpenDataClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Vérifie le filtrage du réseau Tisséo, les perturbations et les horaires.
 */
final class TisseoOpenDataClientTest extends TestCase
{
    public function testReaderKeepsOnlyMetrosTramsAndLineoOne(): void
    {
        $dataset = (new TisseoGtfsReader())->parseFiles($this->gtfsFiles());

        self::assertSame(['line:61', 'line:69', 'line:38', 'line:144'], array_keys($dataset['lines']));
        self::assertSame('metro', $dataset['lines']['line:61']['type']);
        self::assertSame('tram', $dataset['lines']['line:38']['type']);
        self::assertSame('bus', $dataset['lines']['line:144']['type']);
        self::assertArrayNotHasKey('line:174', $dataset['lines']);
    }

    public function testClientCombinesOfficialAlertsAndUpcomingDepartures(): void
    {
        $now = new \DateTimeImmutable('2026-07-30 10:00:00', new \DateTimeZone('Europe/Paris'));
        $realtimeDeparture = $now->modify('+17 minutes')->getTimestamp();
        $responses = [
            new MockResponse($this->gtfsArchive(), ['http_code' => 200]),
            new MockResponse(json_encode([
                'header' => ['timestamp' => $now->getTimestamp()],
                'entity' => [
                    [
                        'id' => 'alert-b',
                        'alert' => [
                            'informed_entity' => [['route_id' => 'line:69']],
                            'header_text' => ['translation' => [['text' => 'B : Ligne perturbée', 'language' => 'fr']]],
                        ],
                    ],
                    [
                        'id' => 'trip-a',
                        'trip_update' => [
                            'trip' => ['trip_id' => 'trip:A'],
                            'stop_time_update' => [[
                                'stop_id' => 'stop:A:jean-jaurès',
                                'departure' => ['time' => $realtimeDeparture],
                            ]],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ];
        $client = new TisseoOpenDataClient(
            new MockHttpClient($responses),
            new ArrayAdapter(),
            new TisseoGtfsReader(),
            new NullLogger(),
        );

        $network = $client->getNetwork(null, $now);
        $selectedStop = current(array_filter(
            $network['stops'],
            static fn (array $stop): bool => $stop['routeId'] === 'line:61'
                && $stop['name'] === 'Jean Jaurès',
        ));
        self::assertIsArray($selectedStop);

        $network = $client->getNetwork($selectedStop['id'], $now);
        $linesById = array_column($network['lines'], null, 'id');

        self::assertTrue($network['available']);
        self::assertTrue($network['realtimeAvailable']);
        self::assertCount(4, $network['lines']);
        self::assertSame('disrupted', $linesById['line:69']['status']);
        self::assertSame('B : Ligne perturbée', $linesById['line:69']['disruption']);
        self::assertSame('ok', $linesById['line:61']['status']);
        self::assertNotEmpty($network['departures']);
        self::assertSame('10:17', $network['departures'][0]['time']->format('H:i'));
        self::assertTrue($network['departures'][0]['realtime']);
        self::assertSame('Balma-Gramont', $network['departures'][0]['headsign']);
    }

    public function testStaticProviderFailureReturnsAnUnavailableNetwork(): void
    {
        $client = new TisseoOpenDataClient(
            new MockHttpClient([new MockResponse('Service indisponible', ['http_code' => 503])]),
            new ArrayAdapter(),
            new TisseoGtfsReader(),
            new NullLogger(),
        );

        $network = $client->getNetwork();

        self::assertFalse($network['available']);
        self::assertFalse($network['realtimeAvailable']);
        self::assertSame([], $network['lines']);
        self::assertSame([], $network['departures']);
    }

    /**
     * @return array<string, string>
     */
    private function gtfsFiles(): array
    {
        return [
            'routes.txt' => implode("\n", [
                'route_id,route_short_name,route_long_name,route_type',
                'line:61,A,Basso Cambo - Balma-Gramont,1',
                'line:69,B,Ramonville - Borderouge,1',
                'line:38,T1,Palais de Justice - MEETT,0',
                'line:144,L1,Sept Deniers - Fonsegrives Entiore,3',
                'line:174,L9,L’Union - Saint-Orens,3',
            ]),
            'trips.txt' => implode("\n", [
                'route_id,service_id,trip_id,trip_headsign,direction_id',
                'line:61,service:weekday,trip:A,Balma-Gramont,0',
                'line:69,service:weekday,trip:B,Borderouge,0',
                'line:38,service:weekday,trip:T1,MEETT,0',
                'line:144,service:weekday,trip:L1,Fonsegrives Entiore,0',
                'line:174,service:weekday,trip:L9,Saint-Orens,0',
            ]),
            'stops.txt' => implode("\n", [
                'stop_id,stop_name',
                'stop:A:jean-jaurès,Jean Jaurès',
                'stop:B:jean-jaurès,Jean Jaurès',
                'stop:T1:arènes,Arènes',
                'stop:L1:capitole,Capitole',
                'stop:L9:verdier,François Verdier',
            ]),
            'stop_times.txt' => implode("\n", [
                'trip_id,arrival_time,departure_time,stop_id,stop_sequence',
                'trip:A,10:15:00,10:15:00,stop:A:jean-jaurès,1',
                'trip:B,10:20:00,10:20:00,stop:B:jean-jaurès,1',
                'trip:T1,10:25:00,10:25:00,stop:T1:arènes,1',
                'trip:L1,10:30:00,10:30:00,stop:L1:capitole,1',
                'trip:L9,10:35:00,10:35:00,stop:L9:verdier,1',
            ]),
            'calendar.txt' => implode("\n", [
                'service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date',
                'service:weekday,1,1,1,1,1,1,1,20260701,20260831',
            ]),
            'calendar_dates.txt' => "service_id,date,exception_type\n",
        ];
    }

    private function gtfsArchive(): string
    {
        $path = sprintf(
            '%s%ssafecity-tisseo-test-%s.zip',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR),
            DIRECTORY_SEPARATOR,
            bin2hex(random_bytes(6)),
        );

        try {
            $archive = new \PharData($path);
            foreach ($this->gtfsFiles() as $filename => $content) {
                $archive->addFromString($filename, $content);
            }
            unset($archive);

            $content = file_get_contents($path);
            self::assertIsString($content);

            return $content;
        } finally {
            // L'archive générée ne sert qu'à simuler le téléchargement officiel.
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}


