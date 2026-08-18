<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Vérifie le comportement couvert par UserChecker.
 */
final class UserCheckerTest extends TestCase
{
    public function testActiveAccountPassesThePreAuthenticationCheck(): void
    {
        $user = new User();
        $user
            ->setAccountActive(true)
            ->setIsVerified(true);

        (new UserChecker())->checkPreAuth($user);

        self::addToAssertionCount(1);
    }

    public function testDisabledAccountIsRejected(): void
    {
        $user = new User();
        $user
            ->setAccountActive(false)
            ->setIsVerified(true);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('auth.account_disabled');

        (new UserChecker())->checkPreAuth($user);
    }

    public function testUnverifiedAccountIsRejected(): void
    {
        $user = new User();
        $user
            ->setAccountActive(true)
            ->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('auth.email_not_verified');

        (new UserChecker())->checkPreAuth($user);
    }
}