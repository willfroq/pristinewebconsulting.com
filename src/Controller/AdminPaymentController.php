<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LessonPayment;
use App\Repository\LessonPaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/payments')]
final class AdminPaymentController extends AbstractController
{
    #[Route('', name: 'app_admin_payments', methods: ['GET'])]
    public function index(LessonPaymentRepository $payments): Response
    {
        return $this->render('admin/payments.html.twig', [
            'payments' => $payments->pending(),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_payment_approve', methods: ['POST'])]
    public function approve(
        LessonPayment $payment,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->validateToken($payment, $request, 'approve');
        if ($payment->approve()) {
            $payment->getStudent()->grantLessonCredit();
            $entityManager->flush();
            $this->addFlash('success', 'Payment approved. Consultation scheduling was unlocked.');
        } else {
            $this->addFlash('info', 'That payment has already been reviewed.');
        }

        return $this->redirectToRoute('app_admin_payments');
    }

    #[Route('/{id}/decline', name: 'app_admin_payment_decline', methods: ['POST'])]
    public function decline(
        LessonPayment $payment,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->validateToken($payment, $request, 'decline');
        if ($payment->decline()) {
            $entityManager->flush();
            $this->addFlash('success', 'Payment submission declined. Scheduling remains locked.');
        }

        return $this->redirectToRoute('app_admin_payments');
    }

    private function validateToken(LessonPayment $payment, Request $request, string $action): void
    {
        if (!$this->isCsrfTokenValid(
            $action.'_payment_'.$payment->getId(),
            $request->request->getString('_token'),
        )) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
    }
}
