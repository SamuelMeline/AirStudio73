<?php

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use App\Entity\CourseDetails;
use App\Form\CourseDetailsType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CourseDetailsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $courseDetails = new CourseDetails();
        $form = $this->createForm(CourseDetailsType::class, $courseDetails);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }
                $courseDetails->setPhoto($newFilename);
            }

            // Handle photoBenefits upload
            $photoBenefitsFile = $form->get('photobenefits')->getData();
            if ($photoBenefitsFile) {
                $originalFilename = pathinfo($photoBenefitsFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newBenefitsFilename = $safeFilename . '-' . uniqid() . '.' . $photoBenefitsFile->guessExtension();

                try {
                    $photoBenefitsFile->move(
                        $this->getParameter('photos_directory'),
                        $newBenefitsFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }
                $courseDetails->setPhotoBenefits($newBenefitsFilename);
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

    #[Route('/cours/{id}', name: 'course_details_show')]
    public function show(CourseDetails $courseDetails, Request $request, EntityManagerInterface $em): Response
    {
        // Création du formulaire d'avis
        $review = new Review();
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Lier l'avis au cours et à l'utilisateur connecté
            $review->setCourseDetails($courseDetails);
            $review->setUser($this->getUser());

            // Sauvegarder l'avis
            $em->persist($review);
            $em->flush();

            // Message de confirmation
            $this->addFlash('success', 'Votre avis a été ajouté avec succès.');

            return $this->redirectToRoute('homepage', ['id' => $courseDetails->getId()]);
        }

        return $this->render('course_details/show.html.twig', [
            'courseDetails' => $courseDetails,
            'reviewForm' => $form->createView(),
        ]);
    }

    #[Route('/cours/{id}/modifier', name: 'course_details_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, EntityManagerInterface $em, CourseDetails $courseDetails, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(CourseDetailsType::class, $courseDetails);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }
                $courseDetails->setPhoto($newFilename);
            }

            // Handle photoBenefits upload
            $photoBenefitsFile = $form->get('photobenefits')->getData();
            if ($photoBenefitsFile) {
                $originalFilename = pathinfo($photoBenefitsFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoBenefitsFile->guessExtension();

                try {
                    $photoBenefitsFile->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }
                $courseDetails->setPhotoBenefits($newFilename);
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
