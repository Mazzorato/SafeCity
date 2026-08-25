<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\ModerationCase;
use App\Entity\Notification;
use App\Entity\Photo;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use App\Enum\NotificationTypeEnum;
use App\Localization\SupportedLocale;
use App\Repository\CommentRepository;
use App\Repository\ModerationCaseRepository;
use App\Repository\PhotoRepository;
use App\Service\ModerationNotifier;
use App\Service\UserNotificationPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Pilote le traitement administratif des contenus signalés.
 */
final class ModerationController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/moderation/comment/{id}/flag', name: 'app_moderation_flag_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function flagComment(
        Comment $comment,
        Request $request,
        ModerationCaseRepository $repository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyContentFlag($comment->getReport(), $comment->getAuthor());

        if (!$this->isCsrfTokenValid('flag_comment_' . $comment->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException($this->translator->trans('security.invalid_token'));
        }

        $this->createCase(
            ModerationTargetEnum::COMMENT,
            (int) $comment->getId(),
            $comment->getAuthor(),
            $request,
            $repository,
            $entityManager,
        );

        return $this->redirectToRoute('app_report_show', [
            'id' => $comment->getReport()?->getId(),
            '_fragment' => 'comments',
        ]);
    }

    #[Route('/moderation/photo/{id}/flag', name: 'app_moderation_flag_photo', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function flagPhoto(
        Photo $photo,
        Request $request,
        ModerationCaseRepository $repository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyContentFlag($photo->getReport(), $photo->getUploader());

        if (!$this->isCsrfTokenValid('flag_photo_' . $photo->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException($this->translator->trans('security.invalid_token'));
        }

        $this->createCase(
            ModerationTargetEnum::PHOTO,
            (int) $photo->getId(),
            $photo->getUploader(),
            $request,
            $repository,
            $entityManager,
        );

        return $this->redirectToRoute('app_report_show', ['id' => $photo->getReport()?->getId()]);
    }

    #[Route('/admin/moderation', name: 'app_admin_moderation', methods: ['GET'])]
    public function index(
        ModerationCaseRepository $repository,
        CommentRepository $comments,
        PhotoRepository $photos,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $schemaReady = true;
        try {
            $cases = $repository->findBy([], ['reportedAt' => 'DESC'], 100);
        } catch (\Throwable) {
            $cases = [];
            $schemaReady = false;
        }

        $items = array_map(
            static fn (ModerationCase $case): array => [
                'case' => $case,
                'target' => $case->getTargetType() === ModerationTargetEnum::COMMENT
                    ? $comments->find($case->getTargetId())
                    : $photos->find($case->getTargetId()),
            ],
            $cases
        );

        return $this->render('admin/moderation/index.html.twig', [
            'items' => $items,
            'schemaReady' => $schemaReady,
            'pendingCount' => count(array_filter(
                $cases,
                static fn (ModerationCase $case): bool => $case->getStatus() === ModerationStatusEnum::FLAGGED
            )),
        ]);
    }

    #[Route(
        '/admin/moderation/{id}/{decision}',
        name: 'app_admin_moderation_decide',
        methods: ['POST'],
        requirements: ['id' => '\d+', 'decision' => 'hide|dismiss|reopen']
    )]
    public function decide(
        ModerationCase $case,
        string $decision,
        Request $request,
        EntityManagerInterface $entityManager,
        ModerationNotifier $notifier,
        UserNotificationPublisher $notificationPublisher,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid(
            'moderation_' . $case->getId() . '_' . $decision,
            $request->getPayload()->getString('_token')
        )) {
            throw $this->createAccessDeniedException($this->translator->trans('security.invalid_token'));
        }

        /** @var User $moderator */
        $moderator = $this->getUser();
        $status = match ($decision) {
            'hide' => ModerationStatusEnum::HIDDEN,
            'dismiss' => ModerationStatusEnum::DISMISSED,
            'reopen' => ModerationStatusEnum::FLAGGED,
        };

        $case
            ->setStatus($status)
            ->setModerator($moderator)
            ->setModeratedAt($decision === 'reopen' ? null : new \DateTimeImmutable());

        $notification = null;
        if ($status === ModerationStatusEnum::HIDDEN && $case->getAuthor() !== null) {
            // Les notifications enregistrées sont localisées selon le profil
            // du destinataire, indépendamment de la langue de l’administrateur.
            $recipientLocale = SupportedLocale::normalize($case->getAuthor()->getProfile()?->getLanguage());
            $notification = (new Notification())
                ->setTitle($this->translator->trans(
                    'notification.moderation_hidden_title',
                    locale: $recipientLocale,
                ))
                ->setMessage($this->translator->trans(
                    'notification.moderation_hidden_message',
                    ['%reason%' => $this->translatedReason($case->getReason(), $recipientLocale)],
                    locale: $recipientLocale,
                ))
                ->setType(NotificationTypeEnum::MODERATION)
                ->setSentAt(new \DateTime())
                ->setIsRead(false)
                ->setRecipient($case->getAuthor());
            $entityManager->persist($notification);
        }

        $entityManager->flush();

        if ($status === ModerationStatusEnum::HIDDEN) {
            $notifier->sendHiddenContentWarning($case);
        }
        if ($notification !== null) {
            $notificationPublisher->publish($notification);
        }

        $this->addFlash('success', $this->translator->trans('flash.moderation_decision_saved'));

        return $this->redirectToRoute('app_admin_moderation');
    }

    private function createCase(
        ModerationTargetEnum $targetType,
        int $targetId,
        ?User $author,
        Request $request,
        ModerationCaseRepository $repository,
        EntityManagerInterface $entityManager,
    ): void {
        try {
            if ($repository->hasOpenCase($targetType, $targetId)) {
                $this->addFlash('warning', $this->translator->trans('flash.moderation_already_open'));

                return;
            }

            /** @var User $reporter */
            $reporter = $this->getUser();
            $case = (new ModerationCase())
                ->setTargetType($targetType)
                ->setTargetId($targetId)
                // La valeur enregistrée est un code stable ; son affichage et
                // les notifications sont traduits au moment de leur lecture.
                ->setReason($this->normalizedReason($request->getPayload()->getString('reason')))
                ->setStatus(ModerationStatusEnum::FLAGGED)
                ->setReportedAt(new \DateTimeImmutable())
                ->setReporter($reporter)
                ->setAuthor($author);

            $entityManager->persist($case);
            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('flash.moderation_submitted'));
        } catch (\Throwable) {
            $this->addFlash('warning', $this->translator->trans('flash.moderation_schema_required'));
        }
    }

    private function denyContentFlag(?Report $report, ?User $author): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        if ($report === null || $user->getCity()?->getId() !== $report->getCity()?->getId()) {
            throw $this->createAccessDeniedException($this->translator->trans('security.content_wrong_city'));
        }
        if ($author?->getId() === $user->getId()) {
            throw $this->createAccessDeniedException($this->translator->trans('security.own_content'));
        }
    }

    private function normalizedReason(string $reason): string
    {
        return in_array($reason, ['spam', 'personal_data', 'dangerous', 'inappropriate'], true)
            ? $reason
            : 'inappropriate';
    }

    /**
     * Assure aussi la traduction des dossiers créés avant l’utilisation de
     * codes de motif indépendants de la langue.
     */
    private function translatedReason(string $reason, string $locale): string
    {
        $reasonCode = match ($reason) {
            'Spam ou contenu répétitif' => 'spam',
            'Données personnelles exposées' => 'personal_data',
            'Contenu dangereux ou trompeur' => 'dangerous',
            'Contenu inapproprié' => 'inappropriate',
            default => $this->normalizedReason($reason),
        };

        return $this->translator->trans('moderation.reason.' . $reasonCode, locale: $locale);
    }
}