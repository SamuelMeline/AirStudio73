<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Form\PhotoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class PhotoController extends AbstractController
{
    #[Route('/photo/add', name: 'photo_add')]
    public function add(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $photo = new Photo();
        $form = $this->createForm(PhotoType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imagePath')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }

                $photo->setImagePath($newFilename);
            }

            $entityManager->persist($photo);
            $entityManager->flush();

            return $this->redirectToRoute('photo_gallery');
        }

        return $this->render('photo/add.html.twig', [
            'photoForm' => $form->createView(),
        ]);
    }

    #[Route('/photo/edit/{id}', name: 'photo_edit')]
    public function edit(Photo $photo, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PhotoType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imagePath')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception during file upload
                }

                $photo->setImagePath($newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'La photo a été modifiée avec succès.');

            return $this->redirectToRoute('photo_gallery');
        }

        return $this->render('photo/edit.html.twig', [
            'photoForm' => $form->createView(),
        ]);
    }

    #[Route('/photo/delete/{id}', name: 'photo_delete')]
    public function delete(Photo $photo, EntityManagerInterface $em): Response
    {
        $em->remove($photo);
        $em->flush();

        $this->addFlash('success', 'La photo a été supprimée avec succès.');

        return $this->redirectToRoute('photo_gallery');
    }

    #[Route('/gallery', name: 'photo_gallery')]
    public function gallery(EntityManagerInterface $entityManager): Response
    {
        $photos = $entityManager->getRepository(Photo::class)->findAll();

        return $this->render('photo/gallery.html.twig', [
            'photos' => $photos,
        ]);
    }
}
