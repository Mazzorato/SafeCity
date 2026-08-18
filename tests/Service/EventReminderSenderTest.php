<?php

namespace App\Tests\Service;

use App\Service\EventReminderSender;
use PHPUnit\Framework\TestCase;

final class EventReminderSenderTest extends TestCase
{
    public function testTheReminderServiceExposesItsEntryPoint(): void
    {
        self::assertTrue(method_exists(EventReminderSender::class, 'sendDue'));
    }
}
