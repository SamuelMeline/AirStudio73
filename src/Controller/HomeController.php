<?php
// src/Controller/HomeController.php

namespace App\Controller;

use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CourseDetailsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(
        CourseDetailsRepository $courseDetailsRepository,
        ReviewRepository $reviewRepository,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Récupérer les avis des cours
        $reviews = $reviewRepository->findBy([], ['createdAt' => 'DESC'], 10); // Derniers 10 avis

        // Calcul de la moyenne des notes
        $totalReviews = count($reviews);
        $averageRating = $totalReviews > 0
            ? array_sum(array_map(fn($review) => $review->getRating(), $reviews)) / $totalReviews
            : 0;

        // Suppression d'un avis (si admin)
        if ($request->query->get('delete')) {
            $reviewId = $request->query->get('delete');
            $reviewToDelete = $reviewRepository->find($reviewId);

            if ($reviewToDelete && $this->isGranted('ROLE_ADMIN')) {
                $em->remove($reviewToDelete);
                $em->flush();

                $this->addFlash('success', 'L\'avis a été supprimé avec succès.');
                return $this->redirectToRoute('homepage');
            } else {
                $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer cet avis.');
            }
        }

        return $this->render('home/index.html.twig', [
            'reviews' => $reviews,
            'averageRating' => $averageRating,
        ]);
    }
}
