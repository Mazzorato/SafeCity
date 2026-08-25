<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Report;
use App\Entity\ReportStatusHistory;
use App\Entity\User;
use App\Enum\ModerationStatusEnum;
use App\Enum\NotificationTypeEnum;
use App\Enum\ReportStatusEnum;
use App\Localization\SupportedLocale;
use App\Repository\ModerationCaseRepository;
use App\Service\ReportRealtimePublisher;
use App\Service\UserNotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin')]
/**
 * Regroupe le tableau de bord et les actions réservées à l’administration.
 */
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager,
        ModerationCaseRepository $moderationCases,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $reportRepository = $entityManager->getRepository(Report::class);
        $moderationReady = true;
        try {
            $moderationPending = $moderationCases->count(['status' => ModerationStatusEnum::FLAGGED]);
        } catch (\Throwable) {
            $moderationPending = 0;
            $moderationReady = false;
        }

        return $this->render('admin/index.html.twig', [
            'stats' => [
                'total' => $reportRepository->count(),
                'reported' => $reportRepository->count(['status' => ReportStatusEnum::REPORTED]),
                'inProgress' => $reportRepository->count(['status' => ReportStatusEnum::IN_PROGRESS]),
                'resolved' => $reportRepository->count(['status' => ReportStatusEnum::RESOLVED]),
                'users' => $entityManager->getRepository(User::class)->count(['accountActive' => true]),
            ],
            'reports' => $reportRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'unreadNotifications' => $entityManager->getRepository(Notification::class)->count(['isRead' => false]),
            'moderationPending' => $moderationPending,
            'moderationReady' => $moderationReady,
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = trim($request->query->getString('query'));
        $queryBuilder = $entityManager->getRepository(User::class)->createQueryBuilder('user')
            ->orderBy('user.registrationDate', 'DESC');

        if ($search !== '') {
            $queryBuilder
                ->where('LOWER(user.firstName) LIKE :search OR LOWER(user.lastName) LIKE :search OR LOWER(user.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $this->render('admin/users.html.twig', [
            'users' => $queryBuilder->getQuery()->getResult(),
            'search' => $search,
        ]);
    }

    #[Route(
        '/report/{id}/status/{status}',
        name: 'app_admin_report_status',
        methods: ['POST'],
        requirements: ['id' => '\d+', 'status' => 'reported|in_progress|resolved']
    )]
    public function updateReportStatus(
        Report $report,
        string $status,
        Request $request,
        EntityManagerInterface $entityManager,
        ReportRealtimePublisher $realtimePublisher,
        UserNotificationPublisher $notificationPublisher,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid(
            'report_status_' . $report->getId() . '_' . $status,
            $request->getPayload()->getString('_token')
        )) {
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        $previousStatus = $report->getStatus();
        $reportStatus = ReportStatusEnum::from($status);

        if ($previousStatus !== $reportStatus) {
            $changedAt = new \DateTime();

            /** @var User $administrator */
            $administrator = $this->getUser();

            // Chaque transition réelle possède sa propre date et son auteur ;
            // cliquer sur le statut déjà actif ne crée pas de doublon.
            $history = (new ReportStatusHistory())
                ->setStatus($reportStatus)
                ->setChangedAt(\DateTimeImmutable::createFromMutable($changedAt))
                ->setChangedBy($administrator);
            $report
                ->setStatus($reportStatus)
                ->setUpdatedAt($changedAt)
                ->addStatusHistory($history);
            $entityManager->persist($history);
        }

        $notification = null;
        $recipient = $report->getReporter();
        if (
            $previousStatus !== $reportStatus
            && $recipient !== null
            && $recipient->getProfile()?->isEmergencyNotifications()
        ) {
            // Une notification persistée est rédigée directement dans la
            // langue du destinataire afin de rester cohérente hors requête.
            $recipientLocale = SupportedLocale::normalize($recipient->getProfile()?->getLanguage());
            $notification = (new Notification())
                ->setTitle($translator->trans(
                    'notification.report_update_title',
                    ['%id%' => $report->getId()],
                    locale: $recipientLocale,
                ))
                ->setMessage($translator->trans(
                    'notification.report_status.' . $reportStatus->value,
                    locale: $recipientLocale,
                ))
                ->setType(NotificationTypeEnum::EMERGENCY)
                ->setSentAt(new \DateTime())
                ->setIsRead(false)
                ->setRecipient($recipient);
            $entityManager->persist($notification);
        }

        $entityManager->flush();
        $realtimePublisher->publish($report, 'report.status_changed');
        if ($notification !== null) {
            $notificationPublisher->publish($notification);
        }

        $this->addFlash('success', $translator->trans(
            'flash.report_status_updated',
            ['%id%' => $report->getId()],
        ));

        $target = $request->getPayload()->getString('target');

        return $this->redirectToRoute($target === 'reports' ? 'app_report_index' : 'app_admin');
    }
}