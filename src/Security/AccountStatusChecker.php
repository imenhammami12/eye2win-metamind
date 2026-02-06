<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\AccountStatus;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AccountStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $status = $user->getAccountStatus();

        // Check if account is banned
        if ($status === AccountStatus::BANNED) {
            throw new CustomUserMessageAccountStatusException(
                'Your account has been permanently banned. You cannot access this application. If you believe this is an error, please contact support.'
            );
        }

        // Check if account is suspended
        if ($status === AccountStatus::SUSPENDED) {
            throw new CustomUserMessageAccountStatusException(
                'Your account has been temporarily suspended. Access is restricted until further review. Please contact support for more information.'
            );
        }

        // Check if account is pending
        if ($status === AccountStatus::PENDING) {
            throw new CustomUserMessageAccountStatusException(
                'Your account is pending approval. Please wait for an administrator to activate your account.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Additional check after authentication
        $status = $user->getAccountStatus();

        if ($status === AccountStatus::BANNED) {
            throw new CustomUserMessageAccountStatusException(
                'Your account has been permanently banned during this session.'
            );
        }

        if ($status === AccountStatus::SUSPENDED) {
            throw new CustomUserMessageAccountStatusException(
                'Your account has been suspended during this session.'
            );
        }
    }
}
