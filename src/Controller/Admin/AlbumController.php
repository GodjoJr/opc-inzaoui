<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Form\AlbumType;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AlbumController extends AbstractController
{
#[Route('/admin/album', name: 'admin_album_index', methods: ['GET'])]
    public function index(Request $request, AlbumRepository $albumRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $albumRepository->findAllPaginated($page, 15);

        return $this->render('admin/album/index.html.twig', [
            'albums' => $pagination['items'],
            'total' => $pagination['total'],
            'currentPage' => $page,
            'totalPages' => $pagination['pages'],
        ]);
    }

    #[Route('/admin/album/add', name: 'admin_album_add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $album = new Album();
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($album);
            $em->flush();

            return $this->redirectToRoute('admin_album_index');
        }

        return $this->render('admin/album/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/album/update/{id}', name: 'admin_album_update')]
    public function update(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $album = $em->getRepository(Album::class)->find($id);

        if (!$album) {
            throw $this->createNotFoundException('Aucun album trouvé pour cet identifiant.');
        }

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_album_index');
        }

        return $this->render('admin/album/update.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/album/delete/{id}', name: 'admin_album_delete')]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        $album = $em->getRepository(Album::class)->find($id);

        if ($album) {
            $em->remove($album);
            $em->flush();
        }

        return $this->redirectToRoute('admin_album_index');
    }
}