<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Form\CoachType;
use App\Repository\CoachRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class CoachController extends AbstractController
{
    #[Route('/coaches', name: 'coach_list')]
    public function list(CoachRepository $coachRepository): Response
    {
        $coaches = $coachRepository->findAll();

        return $this->render('coach/list.html.twig', [
            'coaches' => $coaches,
        ]);
    }

    #[Route('/coach/new', name: 'coach_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $coach = new Coach();
        $form = $this->createForm(CoachType::class, $coach);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }

                $coach->setPhoto($newFilename);
            }

            $em->persist($coach);
            $em->flush();

            $this->addFlash('success', 'Le coach a été créé avec succès.');

            return $this->redirectToRoute('coach_list');
        }

        return $this->render('coach/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/coach/edit/{id}', name: 'coach_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, EntityManagerInterface $em, Coach $coach, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(CoachType::class, $coach);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle photo upload
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('photos_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle exception if something happens during file upload
                }

                $coach->setPhoto($newFilename);
            }

            $em->persist($coach);
            $em->flush();

            $this->addFlash('success', 'Le coach a été mis à jour avec succès.');

            return $this->redirectToRoute('coach_list');
        }

        return $this->render('coach/edit.html.twig', [
            'form' => $form->createView(),
            'coach' => $coach,
        ]);
    }

    #[Route('/coach/delete/{id}', name: 'coach_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(EntityManagerInterface $em, Coach $coach): Response
    {
        $em->remove($coach);
        $em->flush();

        $this->addFlash('success', 'Le coach a été supprimé avec succès.');

        return $this->redirectToRoute('coach_list');
    }
}
