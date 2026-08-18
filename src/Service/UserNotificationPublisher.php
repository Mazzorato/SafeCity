<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publie en temps réel les notifications destinées à un utilisateur.
 */
final class UserNotificationPublisher
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(Notification $notification): void
    {
        $recipient = $notification->getRecipient();
        if ($recipient === null || $notification->getId() === null) {
            return;
        }

        try {
            $payload = json_encode([
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType()?->value,
                'sentAt' => $notification->getSentAt()?->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR);

            $this->hub->publish(new Update(self::topicForUser($recipient), $payload));
        } catch (\Throwable $exception) {
            $this->logger->warning('Publication Mercure de la notification indisponible.', [
                'notification_id' => $notification->getId(),
                'exception' => $exception,
            ]);
        }
    }

    public static function topicForUser(User $user): string
    {
        $token = hash_hmac(
            'sha256',
            'safecity-notifications-' . (string) $user->getId(),
            (string) $user->getPassword()
        );

        return sprintf(
            'https://safecity.local/users/%d/%s/notifications',
            (int) $user->getId(),
            $token
        );
    }
}
