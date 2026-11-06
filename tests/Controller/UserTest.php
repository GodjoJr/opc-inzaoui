<?php

namespace App\Tests\Entity;

use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserGettersAndSetters(): void
    {
        $user = new User();

        $user->setName('Alice Dupont');
        $this->assertSame('Alice Dupont', $user->getName());

        $user->setEmail('alice@example.com');
        $this->assertSame('alice@example.com', $user->getEmail());
        $this->assertSame('alice@example.com', $user->getUserIdentifier());

        $user->setPassword('hashed_pass');
        $this->assertSame('hashed_pass', $user->getPassword());

        $user->setDescription('Une description');
        $this->assertSame('Une description', $user->getDescription());

        $user->setIsActive(true);
        $this->assertTrue($user->isActive());

        $user->setIsActive(false);
        $this->assertFalse($user->isActive());
    }

    public function testAdminRoles(): void
    {
        $admin = new User();
        $admin->setAdmin(true);
        $this->assertTrue($admin->isAdmin());
        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
        $this->assertContains('ROLE_USER', $admin->getRoles());

        $guest = new User();
        $guest->setAdmin(false);
        $this->assertFalse($guest->isAdmin());
        $this->assertSame(['ROLE_USER'], $guest->getRoles());
    }

    public function testMediaCollectionRelations(): void
    {
        $user = new User();
        $media = new Media();
        $media->setUser($user);

        $this->assertSame($user, $media->getUser());
    }
}