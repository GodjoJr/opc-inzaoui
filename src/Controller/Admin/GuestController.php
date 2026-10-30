<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\GuestType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/guests')]
#[IsGranted('ROLE_ADMIN')]
class GuestController extends AbstractController
{
    #[Route('', name: 'admin_guest_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $guests = $em->getRepository(User::class)->findBy(
            ['admin' => false],
            ['name' => 'ASC']
        );

        return $this->render('admin/guest/index.html.twig', [
            'guests' => $guests,
        ]);
    }

    #[Route('/add', name: 'admin_guest_add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $guest = new User();
        $guest->setAdmin(false);
        $guest->setIsActive(true);

        $form = $this->createForm(GuestType::class, $guest, [
            'is_new' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $hasher->hashPassword($guest, $plainPassword);
                $guest->setPassword($hashedPassword);
            }

            $em->persist($guest);
            $em->flush();

            $this->addFlash('success', sprintf('L\'invité "%s" a été créé avec succès.', $guest->getName()));

            return $this->redirectToRoute('admin_guest_index');
        }

        return $this->render('admin/guest/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_guest_toggle_status', methods: ['POST'])]
    public function toggleStatus(User $guest, EntityManagerInterface $em): Response
    {
        if ($guest->isAdmin()) {
            throw $this->createAccessDeniedException('Impossible de modifier le statut d\'un compte administrateur.');
        }

        $guest->setIsActive(!$guest->isActive());
        $em->flush();

        $message = $guest->isActive()
            ? sprintf('L\'accès de %s a été réactivé.', $guest->getName())
            : sprintf('L\'accès de %s a été révoqué.', $guest->getName());

        $this->addFlash('info', $message);

        return $this->redirectToRoute('admin_guest_index');
    }

    #[Route('/{id}/delete', name: 'admin_guest_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $guest,
        EntityManagerInterface $em
    ): Response {
        if ($guest->isAdmin()) {
            throw $this->createAccessDeniedException('Impossible de supprimer un compte administrateur.');
        }

        if ($this->isCsrfTokenValid('delete_guest_' . $guest->getId(), $request->request->get('_token'))) {
            $projectDir = $this->getParameter('kernel.project_dir');

            foreach ($guest->getMedias() as $media) {
                if ($media->getPath()) {
                    $filePath = $projectDir . '/public/' . $media->getPath();
                    if (file_exists($filePath) && is_file($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            $name = $guest->getName();
            $em->remove($guest);
            $em->flush();

            $this->addFlash('success', sprintf('L\'invité "%s" et tous ses contenus associés ont été supprimés.', $name));
        }

        return $this->redirectToRoute('admin_guest_index');
    }
}