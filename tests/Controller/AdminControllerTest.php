<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private function getAdminUser($userRepository): User
    {
        foreach ($userRepository->findAll() as $user) {
            if ($user->isAdmin() || in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }

        $admin = new User();
        $admin->setEmail('admin_test@portfolio.com');
        $admin->setName('Admin Test');
        $admin->setPassword('password');
        $admin->setAdmin(true);
        $admin->setIsActive(true);

        return $admin;
    }

    public function testAdminAccessDeniedForAnonymous(): void
    {
        $client = static::createClient();

        foreach (['/admin/album', '/admin/media', '/admin/guests'] as $url) {
            $client->request('GET', $url);
            $statusCode = $client->getResponse()->getStatusCode();
            $this->assertTrue(
                in_array($statusCode, [302, 401, 403], true),
                sprintf('L\'URL %s doit être protégée pour les utilisateurs anonymes.', $url)
            );
        }
    }

    public function testAdminDashboardAccessForAdminUser(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $adminUser = $this->getAdminUser($userRepository);

        $client->loginUser($adminUser);

        foreach (['/admin/album', '/admin/media', '/admin/guests'] as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful(sprintf('L\'URL %s doit être accessible pour un admin.', $url));
        }
    }

public function testAdminNewFormsAccessForAdminUser(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $adminUser = $this->getAdminUser($userRepository);
        $client->loginUser($adminUser);

        $routesToTry = [
            ['/admin/album/new', '/admin/album/add', '/admin/album/create'],
            ['/admin/media/new', '/admin/media/add', '/admin/media/create'],
            ['/admin/guests/new', '/admin/guests/add', '/admin/guest/new', '/admin/guest/add'],
        ];

        foreach ($routesToTry as $urlGroup) {
            $matched = false;
            foreach ($urlGroup as $url) {
                $client->request('GET', $url);
                $code = $client->getResponse()->getStatusCode();
                if (in_array($code, [200, 302], true)) {
                    $matched = true;
                    break;
                }
            }
            $this->assertTrue($matched, sprintf('Aucune route de création active trouvée pour le groupe %s', json_encode($urlGroup)));
        }
    }
}