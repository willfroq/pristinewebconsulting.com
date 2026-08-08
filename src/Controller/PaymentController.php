<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LessonPayment;
use App\Entity\User;
use App\Repository\LessonPaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly string $paypalPaymentUrl,
    ) {
    }

    #[Route('/lesson-payment', name: 'app_lesson_payment', methods: ['GET'])]
    public function index(LessonPaymentRepository $payments): Response
    {
        $this->requireUser();

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/consulting-payment', name: 'app_consulting_payment', methods: ['GET'])]
    public function consulting(Request $request): Response
    {
        $this->requireUser();

        $options = [
            'architecture-review' => [
                'title' => 'Architecture review',
                'price' => '€250',
                'amount' => 250,
                'description' => 'Pressure-test a proposed architecture, refactor, integration, or data model before you commit. You receive a focused review, clear trade-offs, and written next steps. The final fee depends on the estimated workload.',
            ],
            'performance-audit' => [
                'title' => 'Performance audit',
                'price' => '€750',
                'amount' => 750,
                'description' => 'Identify the bottlenecks affecting page speed, Core Web Vitals, conversion, reliability, and search visibility, then get a prioritised improvement plan. The final fee depends on the estimated workload.',
            ],
            'infrastructure-review' => [
                'title' => 'Infrastructure review',
                'price' => '€1,000',
                'amount' => 1000,
                'description' => 'Review hosting, deployment, security, monitoring, resilience, backups, and cloud spend so you know what is safe, what is fragile, and what to improve first. The final fee depends on the estimated workload.',
            ],
            'code-audit' => [
                'title' => 'Code audit',
                'price' => '€1,500',
                'amount' => 1500,
                'description' => 'Assess maintainability, integrations, data quality, security, technical risk, and delivery friction. You receive practical findings your team can act on. The final fee depends on the estimated workload.',
            ],
        ];
        $selected = $request->query->getString('option');
        $selectedOption = $options[$selected] ?? null;

        return $this->render('consulting/payment.html.twig', [
            'options' => $options,
            'selected_option' => $selectedOption,
            'selected_slug' => array_key_exists($selected, $options) ? $selected : null,
            'paypal_payment_url' => $selectedOption ? $this->paypalUrlForAmount($selectedOption['amount']) : null,
        ]);
    }

    #[Route('/lesson-payment/confirm', name: 'app_payment_confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        LessonPaymentRepository $payments,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->requireUser();
        if (!$this->isCsrfTokenValid('confirm_lesson_payment', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }
        $reference = mb_strtoupper(trim($request->request->getString('paypal_reference')));
        if (!preg_match('/^[A-Z0-9-]{6,40}$/', $reference)) {
            $this->addFlash('error', 'Enter the transaction ID shown in your PayPal receipt.');

            return $this->redirectToRoute('app_lesson_payment');
        }
        if (null !== $payments->findOneBy(['paypalReference' => $reference])) {
            $this->addFlash('error', 'That PayPal transaction ID has already been submitted.');

            return $this->redirectToRoute('app_lesson_payment');
        }

        $entityManager->persist(new LessonPayment($user, $reference));
        $entityManager->flush();
        $this->addFlash('success', 'Payment submitted. I’ll verify it and add another consultation credit when approved.');

        return $this->redirectToRoute('app_lesson_payment');
    }

    private function validPaymentUrl(): ?string
    {
        if (!filter_var($this->paypalPaymentUrl, \FILTER_VALIDATE_URL)) {
            return null;
        }

        return str_starts_with($this->paypalPaymentUrl, 'https://') ? $this->paypalPaymentUrl : null;
    }

    private function paypalUrlForAmount(int $amount): ?string
    {
        $baseUrl = $this->validPaymentUrl();
        if (null === $baseUrl) {
            return null;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $baseUrl = preg_replace('/\/\d+(?:[A-Z]{3})?$/i', '', $baseUrl) ?? $baseUrl;

        return $baseUrl.'/'.$amount.'EUR';
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
