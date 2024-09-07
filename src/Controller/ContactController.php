<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact')]
    public function index(Request $request, LoggerInterface $logger, EntityManagerInterface $em): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info('Form is submitted and valid.');

            // Sauvegarder le contact dans la base de données
            $em->persist($contact);
            $em->flush();

            $email = (new Email())
                ->from('contactAirstudio73@gmail.com') // Utiliser l'adresse email authentifiée
                ->replyTo($contact->getEmail()) // Utiliser l'email fourni par l'utilisateur pour les réponses
                ->to('smelinepro@gmail.com', 'airstudio.73@gmail.com') // Adresse de destination
                ->subject($contact->getSubject())
                ->text($contact->getMessage());

            try {
                // Utiliser explicitement le même transporteur que dans test_mailer.php
                $transport = Transport::fromDsn('smtp://contactAirstudio73@gmail.com:ofnlzwlcprshxdmv@smtp.gmail.com:587');
                $mailer = new Mailer($transport);
                $mailer->send($email);
                $logger->info('Email sent successfully.');
                $this->addFlash('success', 'Votre message a été envoyé.');
            } catch (\Exception $e) {
                $logger->error('Failed to send email: ' . $e->getMessage());
                $this->addFlash('error', 'Échec de l\'envoi du message.');
            }

            return $this->redirectToRoute('contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
