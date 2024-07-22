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
use Stripe\PromotionCode;

class BookingController extends AbstractController
{
    private $courses = [
        'Pole Dance' => [
            'name' => 'Pole Dance',
            'durations' => [
                'annuel_classique' => ['duration' => '1 an', 'stripe_price_id' => 'price_1Pf53cKVs2gnspmUvqqnQngR'],
                'annuel_classique_3x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1Pf77fKVs2gnspmUj9U3cD4T'],
                'annuel_classique_4x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1Pf79rKVs2gnspmUGQ5S9Uhl'],
                'annuel_classique_10x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1Pf7AMKVs2gnspmUF6o09Vz0'],
                'annuel_classique_12x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1Pf7AtKVs2gnspmUjkQuYmHT'],
                'annuel_classique_activite' => ['duration' => '1 an', 'stripe_price_id' => 'price_1Pf7BUKVs2gnspmUisiEbRuJ'],
                'annuel_classique_activite_3x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1PfLNwKVs2gnspmU2qTTwR7s'],
                'annuel_classique_activite_4x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1PfLOdKVs2gnspmUsxC6FyQe'],
                'annuel_classique_activite_10x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1PfLP0KVs2gnspmUSp2SrroS'],
                'annuel_classique_activite_12x' => ['duration' => '1 an', 'stripe_price_id' => 'price_1PfLPjKVs2gnspmUmTvysIwF'],
                'souple' => ['duration' => '3 mois', 'stripe_price_id' => 'price_1PfLSIKVs2gnspmUl66eE8DY'],
                'souple_2x' => ['duration' => '3 mois', 'stripe_price_id' => 'price_1PfLSmKVs2gnspmUQjYu5OT6'],
                'cours_classique_et_essaie' => ['duration' => '1 jour', 'stripe_price_id' => 'price_1PfLQgKVs2gnspmUi7vsHkah'],
                'cours_particulier' => ['duration' => '1 jour', 'stripe_price_id' => 'price_1PfLR2KVs2gnspmUKtqO0QNQ'],
            ],
        ],
    ];

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

            $paymentMode = $form->get('paymentMode')->getData();
            $isRecurrent = $form->get('isRecurrent')->getData();
            $numOccurrences = $form->get('numOccurrences')->getData() ?? 1;
            $promoCode = $form->get('promoCode')->getData();

            $stripePriceId = $this->courses[$course->getName()]['durations'][$paymentMode]['stripe_price_id'];

            $discounts = [];
            if ($promoCode) {
                $promotionCodeId = $this->validatePromoCode($promoCode);
                if ($promotionCodeId) {
                    $discounts = [['promotion_code' => $promotionCodeId]];
                } else {
                    $this->addFlash('error', 'Invalid promo code.');
                    return $this->redirectToRoute('booking_new', ['courseId' => $courseId]);
                }
            }

            // Toujours une quantité de 1 pour éviter la multiplication des prix
            $lineItem = [
                'price' => $stripePriceId,
                'quantity' => 1,
            ];

            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items' => [$lineItem],
                'mode' => 'subscription',
                'discounts' => $discounts,
                'client_reference_id' => json_encode([
                    'courseId' => $courseId,
                    'isRecurrent' => $isRecurrent,
                    'numOccurrences' => $numOccurrences,
                ]),
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
        $booking->setIsRecurrent($isRecurrent);
        $booking->setNumOccurrences($numOccurrences);

        $em->persist($booking);
        $em->flush();

        if ($isRecurrent) {
            $this->createRecurrentBookings($booking, $em, $numOccurrences);
        }

        $this->addFlash('success', 'Your booking has been successfully created.');

        return $this->redirectToRoute('calendar');
    }

    private function createRecurrentBookings(Booking $booking, EntityManagerInterface $em, int $numOccurrences): void
    {
        $course = $booking->getCourse();
        $startTime = $course->getStartTime();
        $startTime = $this->ensureDateTime($startTime);

        $recurrenceInterval = $course->getRecurrenceInterval();

        for ($i = 1; $i < $numOccurrences; $i++) {
            $nextCourseDate = (clone $startTime)->add(new \DateInterval('P' . ($i * $recurrenceInterval) . 'D'));

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

    private function canBook(Course $course): bool
    {
        return count($course->getBookings()) < $course->getCapacity();
    }

    private function validatePromoCode(string $promoCode): ?string
    {
        try {
            $promotionCodes = PromotionCode::all([
                'code' => $promoCode,
                'active' => true,
                'limit' => 1,
            ]);
            foreach ($promotionCodes->data as $promotionCode) {
                if ($promotionCode->coupon->valid) {
                    return $promotionCode->id;
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Route('/booking/cancel', name: 'booking_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('error', 'The payment was canceled.');
        return $this->redirectToRoute('calendar');
    }
}
