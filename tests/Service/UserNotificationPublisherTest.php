<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationTypeEnum;
use App\Service\UserNotificationPublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Vérifie le comportement couvert par UserNotificationPublisher.
 */
final class UserNotificationPublisherTest extends TestCase
{
    public function testItPublishesOnAnOpaqueUserTopic(): void
    {
        $user = (new User())
            ->setEmail('citoyen@example.test')
            ->setPassword('stored-password-hash');
        self::setEntityId($user, 7);

        $notification = (new Notification())
            ->setTitle('Signalement mis à jour')
            ->setMessage('Votre signalement est résolu.')
            ->setType(NotificationTypeEnum::EMERGENCY)
            ->setSentAt(new \DateTime('2026-07-25 14:00:00'))
            ->setIsRead(false)
            ->setRecipient($user);
        self::setEntityId($notification, 19);

        $hub = $this->createMock(HubInterface::class);
        $hub
            ->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                $topic = $update->getTopics()[0] ?? '';
                $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);

                return str_starts_with($topic, 'https://safecity.local/users/7/')
                    && str_ends_with($topic, '/notifications')
                    && !str_contains($topic, 'citoyen')
                    && $payload['id'] === 19
                    && $payload['type'] === 'emergency'
                    && $payload['title'] === 'Signalement mis à jour';
            }))
            ->willReturn('event-id');

        $publisher = new UserNotificationPublisher($hub, $this->createStub(LoggerInterface::class));
        $publisher->publish($notification);
    }

    private static function setEntityId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
