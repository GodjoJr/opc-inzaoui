<?php

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Administrateur (Ina Zaoui)
        $admin = new User();
        $admin->setName('Ina Zaoui');
        $admin->setEmail('ina@inazaoui.com');
        $admin->setAdmin(true);
        $admin->setIsActive(true);
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin1234!'));
        $manager->persist($admin);

        // 2. Invité Actif
        $activeGuest = new User();
        $activeGuest->setName('Paul Actif');
        $activeGuest->setEmail('paul.actif@example.com');
        $activeGuest->setAdmin(false);
        $activeGuest->setIsActive(true);
        $activeGuest->setDescription('Photographe invité actif');
        $activeGuest->setPassword($this->hasher->hashPassword($activeGuest, 'Guest1234!'));
        $manager->persist($activeGuest);

        // 3. Invité Révoqué / Bloqué
        $blockedGuest = new User();
        $blockedGuest->setName('Marc Bloque');
        $blockedGuest->setEmail('marc.bloque@example.com');
        $blockedGuest->setAdmin(false);
        $blockedGuest->setIsActive(false);
        $blockedGuest->setDescription('Photographe invité dont l\'accès est révoqué');
        $blockedGuest->setPassword($this->hasher->hashPassword($blockedGuest, 'Guest1234!'));
        $manager->persist($blockedGuest);

        // 4. Albums
        $albumVoyage = new Album();
        $albumVoyage->setName('Voyages');
        $manager->persist($albumVoyage);

        $albumPortrait = new Album();
        $albumPortrait->setName('Portraits');
        $manager->persist($albumPortrait);

        // 5. Médias - Ina Zaoui (Portfolio par défaut)
        $mediaAdmin = new Media();
        $mediaAdmin->setTitle('Paysage Islandais');
        $mediaAdmin->setPath('uploads/fixtures/admin_landscape.jpg');
        $mediaAdmin->setUser($admin);
        $manager->persist($mediaAdmin);

        // 6. Médias - Invité Actif dans un album
        $mediaActiveGuest = new Media();
        $mediaActiveGuest->setTitle('Désert du Sahara');
        $mediaActiveGuest->setPath('uploads/fixtures/guest_sahara.jpg');
        $mediaActiveGuest->setUser($activeGuest);
        $mediaActiveGuest->setAlbum($albumVoyage);
        $manager->persist($mediaActiveGuest);

        // 7. Médias - Invité Révoqué (doit être masqué côté front)
        $mediaBlockedGuest = new Media();
        $mediaBlockedGuest->setTitle('Photo Cachée Révocable');
        $mediaBlockedGuest->setPath('uploads/fixtures/blocked_hidden.jpg');
        $mediaBlockedGuest->setUser($blockedGuest);
        $mediaBlockedGuest->setAlbum($albumVoyage);
        $manager->persist($mediaBlockedGuest);

        $manager->flush();
    }
}