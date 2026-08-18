<?php

namespace App\Tests\Command;

use App\Command\DeleteExpiredAccountsCommand;
use PHPUnit\Framework\TestCase;

final class DeleteExpiredAccountsCommandTest extends TestCase
{
    public function testTheCleanupCommandExists(): void
    {
        self::assertTrue(class_exists(DeleteExpiredAccountsCommand::class));
    }
}
