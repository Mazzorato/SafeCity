<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque l’authentification des comptes désactivés ou non vérifiés.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isAccountActive()) {
            throw new CustomUserMessageAccountStatusException('auth.account_disabled');
        }

        // La création du compte ne donne accès à l'application qu'après
        // validation du lien signé reçu par e-mail.
        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('auth.email_not_verified');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}