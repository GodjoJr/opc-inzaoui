<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Album;
use App\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminControllerTest extends WebTestCase
{
    private function getAdminUser(UserRepository $userRepository): User
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

    public function testMediaDeleteByOtherGuestIsForbidden(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $paul = $userRepository->findOneBy(['email' => 'paul.actif@example.com']);
        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $adminMedia = $em->getRepository(Media::class)->findOneBy(['user' => $admin]);
        $this->assertNotNull($paul);
        $this->assertNotNull($adminMedia);

        $client->loginUser($paul);
        $crawler = $client->request('GET', '/admin/media');
        $csrfToken = $crawler->filter('input[name="_token"]')->count() > 0
            ? $crawler->filter('input[name="_token"]')->first()->attr('value')
            : static::getContainer()->get('security.csrf.token_manager')->getToken('delete_media_' . $adminMedia->getId())->getValue();

        $client->request('POST', '/admin/media/delete/' . $adminMedia->getId(), [
            '_token' => $csrfToken,
        ]);
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [403, 302], true));

        $em->clear();
        $this->assertNotNull($em->getRepository(Media::class)->find($adminMedia->getId()));
    }

    public function testMediaDeleteByGetMethodReturns405(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $media = $em->getRepository(Media::class)->findOneBy(['user' => $admin]);
        $this->assertNotNull($admin);
        $this->assertNotNull($media);

        $client->loginUser($admin);
        $client->request('GET', '/admin/media/delete/' . $media->getId());
        $this->assertResponseStatusCodeSame(405);
    }

    public function testMediaDeleteByOwnerSucceeds(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $this->assertNotNull($admin);

        $tempMedia = new Media();
        $tempMedia->setTitle('Média Temporaire Test');
        $tempMedia->setPath('uploads/fixtures/temp_delete_test.webp');
        $tempMedia->setUser($admin);
        $em->persist($tempMedia);
        $em->flush();
        $tempId = $tempMedia->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/media');

        $formNode = $crawler->filter(sprintf('form[action="/admin/media/delete/%d"]', $tempId));
        $this->assertGreaterThan(0, $formNode->count(), 'Le formulaire de suppression du média doit être présent.');
        $client->submit($formNode->form());

        $this->assertResponseRedirects('/admin/media');

        $em->clear();
        $this->assertNull($em->getRepository(Media::class)->find($tempId));
    }

    public function testAlbumDeleteKeepsMediasWithNullAlbum(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $this->assertNotNull($admin);

        $tempAlbum = new Album();
        $tempAlbum->setName('Album Temporaire Suppression');
        $em->persist($tempAlbum);

        $tempMedia = new Media();
        $tempMedia->setTitle('Média Conservé Après Suppression Album');
        $tempMedia->setPath('uploads/fixtures/temp_album_media.webp');
        $tempMedia->setUser($admin);
        $tempMedia->setAlbum($tempAlbum);
        $em->persist($tempMedia);
        $em->flush();

        $albumId = $tempAlbum->getId();
        $mediaId = $tempMedia->getId();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/album');

        $formNode = $crawler->filter(sprintf('form[action="/admin/album/delete/%d"]', $albumId));
        $this->assertGreaterThan(0, $formNode->count(), 'Le formulaire de suppression d\'album doit être présent.');
        $client->submit($formNode->form());

        $this->assertResponseRedirects('/admin/album');

        $em->clear();
        $this->assertNull($em->getRepository(Album::class)->find($albumId));
        $preservedMedia = $em->getRepository(Media::class)->find($mediaId);
        $this->assertNotNull($preservedMedia);
        $this->assertNull($preservedMedia->getAlbum());

        $em->remove($preservedMedia);
        $em->flush();
    }

    public function testToggleGuestStatusWithInvalidCsrfTokenIsDenied(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);

        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $paul = $userRepository->findOneBy(['email' => 'paul.actif@example.com']);
        $this->assertNotNull($admin);
        $this->assertNotNull($paul);

        $client->loginUser($admin);
        $client->request('POST', '/admin/guests/toggle/' . $paul->getId(), [
            '_token' => 'jeton_csrf_invalide',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }


    public function testMediaUploadRejectsNonImageAndOversizedFiles(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => 'ina@inazaoui.com']);
        $this->assertNotNull($admin);

        $client->loginUser($admin);

        $txtPath = tempnam(sys_get_temp_dir(), 'test_txt_') . '.txt';
        file_put_contents($txtPath, 'Ceci n est pas une image.');
        $invalidFile = new UploadedFile($txtPath, 'document.txt', 'text/plain', null, true);

        $crawler = $client->request('GET', '/admin/media/add');
        $form = $crawler->filter('form')->form([
            'media[title]' => 'Test Fichier Invalide',
            'media[file]' => $invalidFile,
        ]);
        $client->submit($form);
        @unlink($txtPath);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(
            0,
            $client->getCrawler()->filter('.invalid-feedback, .form-error-message, ul li')->count(),
            'Un message d\'erreur de validation doit s\'afficher pour un fichier non image.'
        );

        $bigPath = tempnam(sys_get_temp_dir(), 'test_big_') . '.jpg';
        file_put_contents($bigPath, str_repeat('0', 2 * 1024 * 1024 + 1024));
        $oversizedFile = new UploadedFile($bigPath, 'trop_lourd.jpg', 'image/jpeg', null, true);

        $crawler = $client->request('GET', '/admin/media/add');
        $form = $crawler->filter('form')->form([
            'media[title]' => 'Test Fichier Trop Lourd',
            'media[file]' => $oversizedFile,
        ]);
        $client->submit($form);
        @unlink($bigPath);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(
            0,
            $client->getCrawler()->filter('.invalid-feedback, .form-error-message, ul li')->count(),
            'Un message d\'erreur de validation doit s\'afficher pour un fichier > 2 Mo.'
        );
    }
}