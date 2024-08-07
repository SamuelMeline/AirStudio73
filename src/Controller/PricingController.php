<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PricingController extends AbstractController
{
    #[Route('/tarifs', name: 'pricing')]
    public function index(): Response
    {
        return $this->render('pricing/index.html.twig');
    }
}
