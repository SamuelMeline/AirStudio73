<?php

namespace App\Controller;

use App\Entity\CourseDetails;
use App\Form\CourseDetailsType;
use App\Repository\CourseDetailsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CourseDetailsController extends AbstractController
{
    #[Route('/cours', name: 'course_details_list')]
    public function list(CourseDetailsRepository $courseDetailsRepository): Response
    {
        $courseDetails = $courseDetailsRepository->findAll();

        return $this->render('course_details/list.html.twig', [
            'courseDetails' => $courseDetails,
        ]);
    }

    #[Route('/cours/nouveau', name: 'course_details_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $courseDetails = new CourseDetails();
        $form = $this->createForm(CourseDetailsType::class, $courseDetails);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image.');
                }
                $courseDetails->setImage($newFilename);
            }

            $em->persist($courseDetails);
            $em->flush();

            $this->addFlash('success', 'Le cours a été créé avec succès.');

            return $this->redirectToRoute('course_details_list');
        }

        return $this->render('course_details/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/cours/{id}/modifier', name: 'course_details_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, EntityManagerInterface $em, CourseDetails $courseDetails): Response
    {
        $form = $this->createForm(CourseDetailsType::class, $courseDetails);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image.');
                }
                $courseDetails->setImage($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Le cours a été mis à jour avec succès.');

            return $this->redirectToRoute('course_details_list');
        }

        return $this->render('course_details/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/cours/{id}/supprimer', name: 'course_details_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(EntityManagerInterface $em, CourseDetails $courseDetails): Response
    {
        $em->remove($courseDetails);
        $em->flush();

        $this->addFlash('success', 'Le cours a été supprimé avec succès.');

        return $this->redirectToRoute('course_details_list');
    }
}
