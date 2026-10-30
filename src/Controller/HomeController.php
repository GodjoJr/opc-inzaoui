<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('front/home.html.twig');
    }

    #[Route('/guests', name: 'guests')]
    public function guests(EntityManagerInterface $em): Response
    {
        $guests = $em->getRepository(User::class)->findBy([
            'admin' => false,
            'isActive' => true,
        ], ['name' => 'ASC']);

        return $this->render('front/guests.html.twig', [
            'guests' => $guests,
        ]);
    }

    #[Route('/guest/{id}', name: 'guest')]
    public function guest(int $id, EntityManagerInterface $em): Response
    {
        $guest = $em->getRepository(User::class)->findOneBy([
            'id' => $id,
            'admin' => false,
            'isActive' => true,
        ]);

        if (!$guest) {
            throw $this->createNotFoundException('Cet invité n\'existe pas ou son accès a été suspendu.');
        }

        return $this->render('front/guest.html.twig', [
            'guest' => $guest,
        ]);
    }

    #[Route('/portfolio/{id}', name: 'portfolio')]
    public function portfolio(EntityManagerInterface $em, ?int $id = null): Response
    {
        $albums = $em->getRepository(Album::class)->findAll();
        $album = $id ? $em->getRepository(Album::class)->find($id) : null;
        $admin = $em->getRepository(User::class)->findOneBy(['admin' => true]);

        if ($album) {
            $medias = $em->createQuery(
                'SELECT m 
                 FROM App\Entity\Media m 
                 JOIN m.user u 
                 WHERE m.album = :album 
                   AND (u.admin = true OR u.isActive = true)'
            )
            ->setParameter('album', $album)
            ->getResult();
        } else {
            $medias = $admin
                ? $em->getRepository(Media::class)->findBy(['user' => $admin])
                : [];
        }

        return $this->render('front/portfolio.html.twig', [
            'albums' => $albums,
            'album'  => $album,
            'medias' => $medias,
        ]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }
}