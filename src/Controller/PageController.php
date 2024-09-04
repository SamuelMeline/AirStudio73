<?php
// src/Controller/PageController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
    #[Route('/reglement-interieur', name: 'reglement_interieur')]
    public function reglementInterieur(): Response
    {
        return $this->render('reglement_interieur.html.twig', [
            'controller_name' => 'PageController',
        ]);
    }

    #[Route('/conditions-generales', name: 'conditions_generales')]
    public function conditionsGenerales(): Response
    {
        return $this->render('conditions_generales.html.twig', [
            'controller_name' => 'PageController',
        ]);
    }

    #[Route('/cgv', name: 'nos_cgv')]
    public function formulaireInscription(): Response
    {
        return $this->render('cgv.html.twig', [
            'controller_name' => 'PageController',
        ]);
    }
}
