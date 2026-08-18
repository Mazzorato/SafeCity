<?php

namespace App\Service;

use App\Entity\Notification;
use App\Enum\NotificationTypeEnum;
use App\Localization\SupportedLocale;
use App\Repository\EventFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Crée une seule notification pour chaque événement favori bientôt commencé.
 */
final class EventReminderSender
{
    public function __construct(
        private EventFavoriteRepository $favoriteRepository,
        private EntityManagerInterface $entityManager,
        private UserNotificationPublisher $notificationPublisher,
        private TranslatorInterface $translator,
    ) {
    }

    public function sendDue(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $favorites = $this->favoriteRepository->findDueReminders(
            $now,
            $now->modify('+24 hours'),
        );
        $notifications = [];

        foreach ($favorites as $favorite) {
            $event = $favorite->getEvent();
            $recipient = $favorite->getEventUser();
            if ($event === null || $recipient === null || $event->getStartedAt() === null) {
                continue;
            }

            // Le rappel est persisté dans la langue choisie par son
            // destinataire, même lorsque la commande tourne sans requête web.
            $locale = SupportedLocale::normalize($recipient->getProfile()?->getLanguage());
            $notification = (new Notification())
                ->setTitle($this->translator->trans(
                    'notification.event_reminder_title',
                    ['%event%' => $event->getTitle()],
                    locale: $locale,
                ))
                ->setMessage($this->translator->trans(
                    'notification.event_reminder_message',
                    [
                        '%event%' => $event->getTitle(),
                        '%date%' => $event->getStartedAt()->format('d/m/Y H:i'),
                        '%location%' => $event->getLocation(),
                    ],
                    locale: $locale,
                ))
                ->setType(NotificationTypeEnum::EVENT)
                ->setSentAt(\DateTime::createFromImmutable($now))
                ->setIsRead(false)
                ->setRecipient($recipient);

            // Le marquage est persisté avec la notification afin qu’un passage
            // ultérieur du planificateur ne crée aucun doublon.
            $favorite->setRemindedAt($now);
            $this->entityManager->persist($notification);
            $notifications[] = $notification;
        }

        if ($notifications === []) {
            return 0;
        }

        $this->entityManager->flush();

        // Mercure intervient après le flush, lorsque chaque notification possède
        // son identifiant ; son indisponibilité n’annule pas la notification.
        foreach ($notifications as $notification) {
            $this->notificationPublisher->publish($notification);
        }

        return count($notifications);
    }
}
