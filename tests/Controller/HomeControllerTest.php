<?php

namespace App\Tests\Controller;

use App\Entity\Album;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testHomePageIsSuccessful(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testAboutPageIsSuccessful(): void
    {
        $this->client->request('GET', '/about');
        $this->assertResponseIsSuccessful();
    }

    public function testGuestsPageDisplaysOnlyActiveGuests(): void
    {
        $crawler = $this->client->request('GET', '/guests');
        $this->assertResponseIsSuccessful();

        // L'invité actif des fixtures doit être affiché
        $this->assertAnySelectorTextContains('h4', 'Paul Actif');

        // L'invité révoqué des fixtures ne doit JAMAIS apparaître
        $this->assertStringNotContainsString('Marc Bloque', $this->client->getResponse()->getContent());
    }

public function testActiveGuestDetailPageIsSuccessful(): void
    {
        $activeGuest = $this->em->getRepository(User::class)->findOneBy(['name' => 'Paul Actif']);
        $this->assertNotNull($activeGuest, 'La fixture de l\'invité actif doit exister.');

        $this->client->request('GET', '/guest/' . $activeGuest->getId());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Paul Actif', $this->client->getResponse()->getContent());
    }

    public function testBlockedGuestDetailPageReturns404(): void
    {
        $blockedGuest = $this->em->getRepository(User::class)->findOneBy(['name' => 'Marc Bloque']);
        $this->assertNotNull($blockedGuest, 'La fixture de l\'invité bloqué doit exister.');

        $this->client->request('GET', '/guest/' . $blockedGuest->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUnknownGuestReturns404(): void
    {
        $this->client->request('GET', '/guest/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPortfolioDefaultPage(): void
    {
        $this->client->request('GET', '/portfolio');
        $this->assertResponseIsSuccessful();
    }

    public function testPortfolioAlbumHidesBlockedGuestMedia(): void
    {
        $album = $this->em->getRepository(Album::class)->findOneBy(['name' => 'Voyages']);
        $this->assertNotNull($album, 'L\'album des fixtures doit exister.');

        $this->client->request('GET', '/portfolio/' . $album->getId());
        $this->assertResponseIsSuccessful();

        // La photo de l'invité actif doit être présente
        $this->assertStringContainsString('Désert du Sahara', $this->client->getResponse()->getContent());

        // La photo de l'invité révoqué ne doit pas être affichée
        $this->assertStringNotContainsString('Photo Cachée Révocable', $this->client->getResponse()->getContent());
    }
}