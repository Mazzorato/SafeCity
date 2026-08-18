<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\ModerationCase;
use App\Entity\Photo;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ModerationStatusEnum;
use App\Enum\ModerationTargetEnum;
use App\Repository\ModerationCaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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
}
