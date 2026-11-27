<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\User;
use App\Form\MediaType;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MediaController extends AbstractController
{
    #[Route('/admin/media', name: 'admin_media_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $criteria = [];

        if (!$this->isGranted('ROLE_ADMIN')) {
            $criteria['user'] = $this->getUser();
        }

        $offset = ($page - 1) * 25;

        $medias = $em->getRepository(Media::class)->findBy(
            $criteria,
            ['id' => 'ASC'],
            25,
            $offset
        );
        $total = $em->getRepository(Media::class)->count($criteria);

        return $this->render('admin/media/index.html.twig', [
            'medias' => $medias,
            'total' => $total,
            'page' => $page,
        ]);
    }

    #[Route('/admin/media/add', name: 'admin_media_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $em, ImageUploader $imageUploader): Response
    {
        $media = new Media();
        $form = $this->createForm(MediaType::class, $media, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $user = $this->getUser();
                if ($user instanceof User) {
                    $media->setUser($user);
                }
            }

            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();

            if ($file) {
                $relativePath = $imageUploader->uploadAndConvertToWebp($file);
                $media->setPath($relativePath);
            }

            $em->persist($media);
            $em->flush();

            $this->addFlash('success', 'Le média a été ajouté et optimisé au format WebP.');

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/media/delete/{id}', name: 'admin_media_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Media $media,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadsDir
    ): Response {
        if (!$this->isCsrfTokenValid('delete_media_' . $media->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $media->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres médias.');
        }

        $filePath = $uploadsDir . '/' . basename((string) $media->getPath());
        if (is_file($filePath)) {
            unlink($filePath);
        }

        $em->remove($media);
        $em->flush();

        $this->addFlash('success', 'Le média a été supprimé avec succès.');

        return $this->redirectToRoute('admin_media_index');
    }
}