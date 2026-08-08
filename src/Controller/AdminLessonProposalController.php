<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LessonBooking;
use App\Repository\LessonBookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lesson-proposals')]
final class AdminLessonProposalController extends AbstractController
{
    #[Route('', name: 'app_admin_lesson_proposals', methods: ['GET'])]
    public function index(LessonBookingRepository $bookings): Response
    {
        return $this->render('admin/lesson_proposals.html.twig', [
            'proposals' => $bookings->pendingProposals(),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_lesson_proposal_approve', methods: ['POST'])]
    public function approve(
        LessonBooking $proposal,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'approve_lesson_'.$proposal->getId(),
            $request->request->getString('_token'),
        )) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
        if (!$proposal->isProposed()) {
            $this->addFlash('info', 'That lesson proposal has already been handled.');

            return $this->redirectToRoute('app_admin_lesson_proposals');
        }

        $proposal->approve();
        $entityManager->flush();
        $this->addFlash('success', 'Lesson approved and added to the learner’s dashboard.');

        return $this->redirectToRoute('app_admin_lesson_proposals');
    }

    #[Route('/{id}/decline', name: 'app_admin_lesson_proposal_decline', methods: ['POST'])]
    public function decline(
        LessonBooking $proposal,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid(
            'decline_lesson_'.$proposal->getId(),
            $request->request->getString('_token'),
        )) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
        if ($proposal->isProposed()) {
            $proposal->getStudent()->grantLessonCredit();
            $entityManager->remove($proposal);
            $entityManager->flush();
            $this->addFlash('success', 'Proposal declined. The learner’s lesson credit was returned.');
        }

        return $this->redirectToRoute('app_admin_lesson_proposals');
    }
}
