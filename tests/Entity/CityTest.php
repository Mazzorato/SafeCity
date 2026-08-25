<?php

namespace App\Tests\Entity;

use App\Entity\City;
use App\Entity\Report;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la synchronisation en mémoire entre une ville et ses signalements.
 */
final class CityTest extends TestCase
{
    public function testRemovingReportDetachesBothSides(): void
    {
        $city = new City();
        $report = new Report();

        $city->addReport($report);
        $city->removeReport($report);

        // La collection et le côté propriétaire doivent toujours évoluer ensemble.
        self::assertFalse($city->getReports()->contains($report));
        self::assertNull($report->getCity());
    }
}