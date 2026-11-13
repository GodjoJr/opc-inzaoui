<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('front/home.html.twig');
    }

    #[Route('/guests', name: 'guests', methods: ['GET'])]
    public function guests(Request $request, UserRepository $userRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $userRepository->findActiveGuestsPaginated($page, 10);

        return $this->render('front/guests.html.twig', [
            'guests' => $pagination['items'],
            'currentPage' => $page,
            'totalPages' => $pagination['pages'],
        ]);
    }

    #[Route('/guest/{id}', name: 'guest', methods: ['GET'])]
    public function guest(int $id, Request $request, UserRepository $userRepository, MediaRepository $mediaRepository): Response
    {
        $guest = $userRepository->findOneBy(['id' => $id, 'admin' => false, 'isActive' => true]);

        if (!$guest) {
            throw $this->createNotFoundException('Invité introuvable ou inactif.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $mediaPagination = $mediaRepository->findByUserPaginated($guest, $page, 12);

        return $this->render('front/guest.html.twig', [
            'guest' => $guest,
            'medias' => $mediaPagination['items'],
            'totalMedias' => $mediaPagination['total'],
            'currentPage' => $page,
            'totalPages' => $mediaPagination['pages'],
        ]);
    }

#[Route('/portfolio/{id}', name: 'portfolio', defaults: ['id' => null], methods: ['GET'])]
    public function portfolio(
        ?int $id,
        Request $request,
        AlbumRepository $albumRepository,
        MediaRepository $mediaRepository
    ): Response {
        $albums = $albumRepository->findAll();
        $album = $id ? $albumRepository->find($id) : null;

        if ($id && !$album) {
            throw $this->createNotFoundException('Album introuvable.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $mediaRepository->findPortfolioPaginated($album, $page, 12);

        return $this->render('front/portfolio.html.twig', [
            'albums' => $albums,
            'album' => $album,
            'medias' => $pagination['items'],
            'currentPage' => $page,
            'totalPages' => $pagination['pages'],
        ]);
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }
}