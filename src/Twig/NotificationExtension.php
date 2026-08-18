<?php

namespace App\Twig;

use App\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_topic', static function (User $user): string {
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
            }),
        ];
    }
}

