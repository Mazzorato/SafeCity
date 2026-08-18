<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Vérifie que les tâches déclarées disposent d'un worker local exécutable.
 */
final class SchedulerConfigurationTest extends KernelTestCase
{
    public function testSchedulerRegistersBothTasksAndWorkerConsumesIt(): void
    {
        static::bootKernel();
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('debug:scheduler'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('app:delete-expired-accounts', $tester->getDisplay());
        self::assertStringContainsString('app:send-event-reminders', $tester->getDisplay());

        $composerPath = \dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $workerScript = implode(' ', $composer['scripts']['worker:local'] ?? []);

        self::assertStringContainsString('messenger:consume async scheduler_default', $workerScript);
    }
}
