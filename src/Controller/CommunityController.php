<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Report;
use App\Enum\ReportStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommunityController extends AbstractController
{
    #[Route('/community', name: 'app_community')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $reports = [];
        $comments = [];
        $stats = ['active' => 0, 'thisMonth' => 0, 'resolved' => 0];

        if ($city) {
            $reports = $em->getRepository(Report::class)->findBy(
                ['city' => $city],
                ['createdAt' => 'DESC']
            );

            $now = new \DateTime();
            $startOfMonth = new \DateTime($now->format('Y-m-01') . '    00:00:00');

            foreach ($reports as $report){
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

            $reportIds = array_map(fn(Report $r) => $r->getId(), $reports);

            if ($reportIds) {
                $comments = $em->getRepository(Comment::class)->createQueryBuilder('c')
                    ->where('c.report IN (:reportIds)')
                    ->setParameter('reportIds', $reportIds)
                    ->orderBy('c.createdAt', 'DESC')
                    ->setMaxResults(5)
                    ->getQuery()
                    ->getResult();
            }
        }

        return $this->render('community/index.html.twig', [
            'reports' => $reports,
            'comments' => $comments,
            'stats' => $stats,
            'city' => $city
        ]);
    }
}
