<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    public function testCheckPreAuthWithActiveUserDoesNothing(): void
    {
        $user = new User();
        $user->setIsActive(true);

        $this->checker->checkPreAuth($user);
        $this->assertTrue($user->isActive());
    }

    public function testCheckPreAuthWithInactiveUserThrowsException(): void
    {
        $user = new User();
        $user->setIsActive(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte a été suspendu. Veuillez contacter l\'administrateur.');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthWithNonAppUserDoesNothing(): void
    {
        $otherUser = $this->createMock(UserInterface::class);
        $this->checker->checkPreAuth($otherUser);
        $this->assertTrue(true);
    }

    public function testCheckPostAuthWithActiveUserDoesNothing(): void
    {
        $user = new User();
        $user->setIsActive(true);

        $this->checker->checkPostAuth($user);
        $this->assertTrue(true);
    }

    public function testCheckPostAuthWithInactiveUserThrowsException(): void
    {
        $user = new User();
        $user->setIsActive(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte a été suspendu.');

        $this->checker->checkPostAuth($user);
    }

    public function testCheckPostAuthWithNonAppUserDoesNothing(): void
    {
        $otherUser = $this->createMock(UserInterface::class);
        $this->checker->checkPostAuth($otherUser);
        $this->assertTrue(true);
    }
}