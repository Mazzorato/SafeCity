<?php

namespace App\Service;

use App\Entity\Report;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publie les mises à jour des signalements vers Mercure.
 */
final class ReportRealtimePublisher
{
    private const TOPIC_TEMPLATE = 'https://safecity.local/cities/%d/reports';

    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(Report $report, string $action): void
    {
        $cityId = $report->getCity()?->getId();
        if ($cityId === null || $report->getId() === null) {
            return;
        }

        try {
            $payload = json_encode([
                'action' => $action,
                'report' => [
                    'id' => $report->getId(),
                    'status' => $report->getStatus()?->value,
                    'category' => $report->getCategory()?->getIcon(),
                    'latitude' => $report->getLatitude(),
                    'longitude' => $report->getLongitude(),
                    'updatedAt' => $report->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                ],
            ], JSON_THROW_ON_ERROR);

            $this->hub->publish(new Update(self::topicForCityId($cityId), $payload));
        } catch (\Throwable $exception) {
            $this->logger->warning('Publication Mercure indisponible pour le signalement.', [
                'report_id' => $report->getId(),
                'exception' => $exception,
            ]);
        }
    }

    public static function topicForCityId(int $cityId): string
    {
        return sprintf(self::TOPIC_TEMPLATE, $cityId);
    }
}
