<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\UserNotificationPublisher;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose à Twig les informations utiles au compteur de notifications.
 */
final class NotificationExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'notification_topic',
                static fn (User $user): string => UserNotificationPublisher::topicForUser($user)
            ),
        ];
    }
}
