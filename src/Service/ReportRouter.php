<?php

namespace App\Service;

use App\Entity\Report;
use App\Entity\RoutingRule;
use App\Repository\RoutingRuleRepository;
use Psr\Log\LoggerInterface;

/**
 * Sélectionne le service d’urgence destinataire selon les règles actives.
 */
final class ReportRouter
{
    public function __construct(
        private RoutingRuleRepository $routingRules,
        private LoggerInterface $logger,
    ) {
    }

    public function route(Report $report): ?RoutingRule
    {
        if ($report->getEmergencyService() !== null) {
            return null;
        }

        try {
            $rule = $this->routingRules->findMatching($report);
            if ($rule === null || $rule->getEmergencyService() === null) {
                return null;
            }

            $report->setEmergencyService($rule->getEmergencyService());

            return $rule;
        } catch (\Throwable $exception) {
            $this->logger->warning('Routage automatique indisponible, attribution manuelle conservée.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }
}


