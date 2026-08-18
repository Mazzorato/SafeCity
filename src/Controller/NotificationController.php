<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/notifications')]
/**
 * Affiche et marque les notifications de l’utilisateur.
 */
final class NotificationController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $notifications = $repository->findBy(
            ['recipient' => $user],
            ['sentAt' => 'DESC'],
            100
        );

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => count(array_filter(
                $notifications,
                static fn (Notification $notification): bool => !$notification->isRead()
            )),
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function read(
        Notification $notification,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyNotificationOwner($notification);

        if (!$this->isCsrfTokenValid('notification_read_' . $notification->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException($this->translator->trans('security.invalid_token'));
        }

        $notification->setIsRead(true);
        $entityManager->flush();

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function readAll(
        Request $request,
        NotificationRepository $repository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('notifications_read_all', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException($this->translator->trans('security.invalid_token'));
        }

        /** @var User $user */
        $user = $this->getUser();
        foreach ($repository->findBy(['recipient' => $user, 'isRead' => false]) as $notification) {
            $notification->setIsRead(true);
        }
        $entityManager->flush();

        return $this->redirectToRoute('app_notifications');
    }

    private function denyNotificationOwner(Notification $notification): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        if ($notification->getRecipient()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException($this->translator->trans('security.notification_not_owned'));
        }
    }
}
