<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Report;
use App\Enum\ReportStatusEnum;
use App\Service\ModerationVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche le fil communautaire des signalements visibles.
 */
final class CommunityController extends AbstractController
{
    #[Route('/community', name: 'app_community')]
    public function index(
        EntityManagerInterface $em,
        Request $request,
        ModerationVisibility $moderationVisibility,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $status = $request->query->get('status', 'all');
        $allowedStatuses = ['all', ...array_map(
            static fn (ReportStatusEnum $reportStatus): string => $reportStatus->value,
            ReportStatusEnum::cases()
        )];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $reports = [];
        $comments = [];
        $stats = ['active' => 0, 'thisMonth' => 0, 'resolved' => 0];

        if ($city) {
            $allReports = $em->getRepository(Report::class)->findBy(
                ['city' => $city],
                ['createdAt' => 'DESC']
            );

            $now = new \DateTime();
            $startOfMonth = new \DateTime($now->format('Y-m-01') . ' 00:00:00');

            foreach ($allReports as $report){
                if ($report->getStatus() !== ReportStatusEnum::RESOLVED) {
                    $stats['active']++;
                }
                if ($report->getStatus() === ReportStatusEnum::RESOLVED) {
                    $stats['resolved']++;
                }
                if ($report->getCreatedAt() >= $startOfMonth) {
                    $stats['thisMonth']++;
                }
            }

            $reports = $status === 'all'
                ? $allReports
                : array_filter($allReports, fn(Report $r) => $r->getStatus()->value === $status);

            // Réutilise la règle de visibilité centrale pour empêcher un contenu masqué
            // de réapparaître dans le bloc des commentaires récents.
            foreach ($allReports as $report) {
                array_push($comments, ...$moderationVisibility->visibleComments($report));
            }

            usort(
                $comments,
                static fn (Comment $first, Comment $second): int => $second->getCreatedAt() <=> $first->getCreatedAt()
            );
            $comments = array_slice($comments, 0, 5);
        }

        return $this->render('community/index.html.twig', [
            'reports' => $reports,
            'comments' => $comments,
            'stats' => $stats,
            'city' => $city,
            'status' => $status,
        ]);
    }
}
