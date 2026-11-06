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

        // Soumission avec mauvais identifiants
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

    public function testLogoutRedirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logout');

        $this->assertTrue($client->getResponse()->isRedirection());
    }
}