<?php

namespace App\Tests\Service;

use App\Entity\City;
use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Enum\ReportStatusEnum;
use App\Service\ReportRealtimePublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Vérifie le comportement couvert par ReportRealtimePublisher.
 */
final class ReportRealtimePublisherTest extends TestCase
{
    public function testItPublishesTheReportOnTheCityTopic(): void
    {
        $city = new City();
        $report = (new Report())
            ->setCity($city)
            ->setCategory((new ReportCategory())->setIcon('road'))
            ->setStatus(ReportStatusEnum::IN_PROGRESS)
            ->setLatitude('43.6045000')
            ->setLongitude('1.4440000')
            ->setUpdatedAt(new \DateTime('2026-07-25 12:30:00'));

        self::setEntityId($city, 31);
        self::setEntityId($report, 42);

        $hub = $this->createMock(HubInterface::class);
        $hub
            ->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);

                return $update->getTopics() === ['https://safecity.local/cities/31/reports']
                    && $payload['action'] === 'report.status_changed'
                    && $payload['report']['id'] === 42
                    && $payload['report']['status'] === 'in_progress'
                    && $payload['report']['category'] === 'road'
                    && $payload['report']['latitude'] === '43.6045000'
                    && $payload['report']['longitude'] === '1.4440000';
            }))
            ->willReturn('event-id');

        $publisher = new ReportRealtimePublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publish($report, 'report.status_changed');
    }

    private static function setEntityId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
