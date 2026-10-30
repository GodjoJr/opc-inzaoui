<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Si l'invité a été désactivé par Ina
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Veuillez contacter l\'administrateur.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Vérification post-authentification si nécessaire
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu.'
            );
        }
    }
}