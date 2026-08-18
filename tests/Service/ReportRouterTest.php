<?php

namespace App\Tests\Service;

use App\Entity\EmergencyService;
use App\Entity\Report;
use App\Entity\RoutingRule;
use App\Repository\RoutingRuleRepository;
use App\Service\ReportRouter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Vérifie le comportement couvert par ReportRouter.
 */
final class ReportRouterTest extends TestCase
{
    public function testItAssignsTheServiceSelectedByTheRule(): void
    {
        $report = new Report();
        $service = new EmergencyService();
        $rule = (new RoutingRule())->setEmergencyService($service);

        $repository = $this->createMock(RoutingRuleRepository::class);
        $repository
            ->expects(self::once())
            ->method('findMatching')
            ->with($report)
            ->willReturn($rule);

        $router = new ReportRouter($repository, $this->createStub(LoggerInterface::class));

        self::assertSame($rule, $router->route($report));
        self::assertSame($service, $report->getEmergencyService());
    }

    public function testItNeverOverwritesAManualAssignment(): void
    {
        $service = new EmergencyService();
        $report = (new Report())->setEmergencyService($service);

        $repository = $this->createMock(RoutingRuleRepository::class);
        $repository->expects(self::never())->method('findMatching');

        $router = new ReportRouter($repository, $this->createStub(LoggerInterface::class));

        self::assertNull($router->route($report));
        self::assertSame($service, $report->getEmergencyService());
    }
}


