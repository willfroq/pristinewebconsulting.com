<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LessonBooking;
use App\Entity\User;
use App\Repository\LessonBookingRepository;
use App\Service\LessonSchedule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    #[Route('/booking', name: 'app_booking')]
    public function index(): Response
    {
        $user = $this->requireUser();
        if (!$user->hasLessonCredit()) {
            $this->addFlash('info', 'Submit your PayPal transaction for approval before scheduling.');

            return $this->redirectToRoute('app_consulting_payment');
        }

        return $this->render('booking/index.html.twig');
    }

    #[Route('/booking/reserve', name: 'app_booking_reserve', methods: ['POST'])]
    public function reserve(
        Request $request,
        LessonSchedule $schedule,
        LessonBookingRepository $bookings,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->requireUser();
        if (!$user->hasLessonCredit()) {
            throw $this->createAccessDeniedException('A paid lesson credit is required.');
        }
        if (!$this->isCsrfTokenValid('reserve_lesson', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $date = $request->request->getString('date');
        $time = $request->request->getString('time');
        $topic = $request->request->getString('topic', 'Architecture and technical decisions');
        $otherTopic = trim($request->request->getString('other_topic'));
        $allowedTopics = ['Architecture and technical decisions', 'Website performance', 'Infrastructure and AWS', 'Code quality and maintainability', 'Other'];
        if ('Other' === $topic) {
            if (mb_strlen($otherTopic) < 3 || mb_strlen($otherTopic) > 80) {
                $this->addFlash('error', 'Describe your consulting focus in 3 to 80 characters.');

                return $this->redirectToRoute('app_booking');
            }
            $topic = $otherTopic;
        } elseif (!in_array($topic, $allowedTopics, true)) {
            $topic = 'Architecture and technical decisions';
        }

        try {
            $startsAt = new \DateTimeImmutable($date.' '.$time, new \DateTimeZone('Europe/Amsterdam'));
        } catch (\Exception) {
            $this->addFlash('error', 'Choose a valid consultation time.');

            return $this->redirectToRoute('app_booking');
        }

        if (!$schedule->isValidSlot($startsAt)) {
            $this->addFlash('error', 'That time is no longer available. Please choose another.');

            return $this->redirectToRoute('app_booking');
        }

        // Lesson choices are entered in Netherlands time, but persisted as UTC.
        $startsAt = $startsAt->setTimezone(new \DateTimeZone('UTC'));
        if (null !== $bookings->findOneBy(['startsAt' => $startsAt])) {
            $this->addFlash('error', 'That time is no longer available. Please choose another.');

            return $this->redirectToRoute('app_booking');
        }

        $user->consumeLessonCredit();
        $entityManager->persist(new LessonBooking($user, $startsAt, $topic));
        $entityManager->flush();
        $this->addFlash('success', 'Consultation time proposed. It will appear as scheduled after approval.');

        return $this->redirectToRoute('app_dashboard');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
