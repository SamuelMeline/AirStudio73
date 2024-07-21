<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Course;
use App\Form\BookingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class BookingController extends AbstractController
{
    #[Route('/booking/new/{courseId}', name: 'booking_new')]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em, int $courseId): Response
    {
        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            Stripe::setApiKey($this->getParameter('stripe.secret_key'));

            $isRecurrent = $booking->isRecurrent();
            $numOccurrences = $booking->getNumOccurrences();
            
            if ($isRecurrent) {
                $startTime = $course->getStartTime();
                $startTime = $this->ensureDateTime($startTime);
                $totalOccurrences = $this->calculateOccurrences($course->getRecurrenceDuration(), $startTime);

                // Si le nombre d'occurrences n'est pas spécifié, réserver tous les cours disponibles
                if (!$numOccurrences) {
                    $numOccurrences = $totalOccurrences;
                }

                $numOccurrences = min($numOccurrences, $totalOccurrences);
            } else {
                $numOccurrences = 1; // Si non récurrent, une seule occurrence
            }

            $totalAmount = $course->getPrice() * 100 * $numOccurrences;

            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $course->getName(),
                        ],
                        'unit_amount' => $course->getPrice() * 100,
                    ],
                    'quantity' => $numOccurrences,
                ]],
                'mode' => 'payment',
                'client_reference_id' => json_encode(['courseId' => $courseId, 'isRecurrent' => $isRecurrent, 'numOccurrences' => $numOccurrences]),
                'success_url' => $this->generateUrl('booking_success', [
                    'courseId' => $courseId,
                    'isRecurrent' => $isRecurrent,
                    'numOccurrences' => $numOccurrences,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $this->generateUrl('booking_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

            return $this->redirect($session->url);
        }

        return $this->render('booking/new.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
            'stripe_public_key' => $this->getParameter('stripe.public_key'),
        ]);
    }

    #[Route('/booking/success', name: 'booking_success')]
    #[IsGranted('ROLE_USER')]
    public function success(Request $request, EntityManagerInterface $em): Response
    {
        $courseId = $request->query->get('courseId');
        $isRecurrent = $request->query->get('isRecurrent');
        $numOccurrences = $request->query->get('numOccurrences');

        $course = $em->getRepository(Course::class)->find($courseId);

        if (!$course) {
            throw $this->createNotFoundException('No course found for id ' . $courseId);
        }

        $booking = new Booking();
        $booking->setCourse($course);
        $booking->setUserName($this->getUser()->getUserIdentifier());
        $booking->setNumOccurrences($numOccurrences);

        $em->persist($booking);
        $em->flush();

        if ($isRecurrent && $course->isRecurrent()) {
            $this->createRecurrentBookings($booking, $em, $numOccurrences);
        }

        $this->addFlash('success', 'Your booking has been successfully created.');

        return $this->redirectToRoute('calendar');
    }

    #[Route('/booking/cancel', name: 'booking_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('error', 'The payment was canceled.');
        return $this->redirectToRoute('calendar');
    }

    private function canBook(Course $course): bool
    {
        return count($course->getBookings()) < $course->getCapacity();
    }

    private function createRecurrentBookings(Booking $booking, EntityManagerInterface $em, int $numOccurrences): void
    {
        $course = $booking->getCourse();
        $startTime = $course->getStartTime();
        $startTime = $this->ensureDateTime($startTime);

        $recurrenceDuration = $course->getRecurrenceDuration();
        $recurrenceInterval = $course->getRecurrenceInterval();

        for ($i = 1; $i < $numOccurrences; $i++) {
            $nextCourseDate = new \DateTime();
            $nextCourseDate->setTimestamp($startTime->getTimestamp() + ($i * $recurrenceInterval * 24 * 60 * 60));

            $recurrentCourse = $em->getRepository(Course::class)->findOneBy([
                'name' => $course->getName(),
                'startTime' => $nextCourseDate,
            ]);

            if ($recurrentCourse && $this->canBook($recurrentCourse)) {
                $newBooking = new Booking();
                $newBooking->setUserName($booking->getUserName());
                $newBooking->setCourse($recurrentCourse);
                $newBooking->setIsRecurrent(true);
                $em->persist($newBooking);
            }
        }

        $em->flush();
    }

    private function calculateOccurrences(string $recurrenceDuration, \DateTimeInterface $startDate): int
    {
        switch ($recurrenceDuration) {
            case '1_month':
                return 4;
            case '3_months':
                return 12;
            case '6_months':
                return 24;
            case '1_year':
                return 52;
            case '2_years':
                return 104;
            case '3_years':
                return 156;
            default:
                return 4;
        }
    }

    private function ensureDateTime($startTime): \DateTimeInterface
    {
        if ($startTime instanceof \DateTimeInterface) {
            return $startTime;
        }

        if (is_string($startTime)) {
            return new \DateTime($startTime);
        }

        return new \DateTime('now');
    }
}
