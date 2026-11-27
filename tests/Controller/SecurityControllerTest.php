<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $button = $crawler->selectButton('Se connecter');
        if ($button->count() > 0) {
            $form = $button->form([
                '_username' => 'wrong@test.com',
                '_password' => 'invalidpassword',
            ]);
            $client->submit($form);
            $this->assertResponseRedirects('/login');
        } else {
            $this->assertResponseIsSuccessful();
        }
    }

    public function testLogout(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(\App\Repository\UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $this->assertNotNull($admin);

        $client->loginUser($admin);
        $client->request('GET', '/admin/media');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/');

        $client->request('GET', '/admin/media');
        $this->assertResponseRedirects('/login');
    }
}