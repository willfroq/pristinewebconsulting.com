<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ConsultationRequestType;
use App\Service\ConsultationContentFilter;
use App\Service\ConsultationSubmissionLimiter;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HomeController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface|RandomException
     */
    #[Route('/', name: 'app_home')]
    public function index(Request $request, MailerInterface $mailer, ConsultationContentFilter $contentFilter, ConsultationSubmissionLimiter $submissionLimiter): Response
    {
        $session = $request->getSession();
        $formToken = bin2hex(random_bytes(32));
        $issuedTokens = $session->get('consultation_form_tokens', []);
        $issuedTokens = is_array($issuedTokens) ? $issuedTokens : [];
        $now = time();
        $issuedTokens = array_filter($issuedTokens, static fn (mixed $issuedAt): bool => is_int($issuedAt) && $issuedAt > $now - 7200);
        $issuedTokens[$formToken] = $now;
        $session->set('consultation_form_tokens', $issuedTokens);

        $form = $this->createForm(ConsultationRequestType::class, null, ['form_token' => $formToken]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, email: string, website: string|null, message: string} $data */
            $data = $form->getData();
            $submittedToken = $form->get('form_token')->getData();
            $issuedAt = is_string($submittedToken) ? ($issuedTokens[$submittedToken] ?? null) : null;
            if (is_string($submittedToken)) {
                unset($issuedTokens[$submittedToken]);
            }
            $session->set('consultation_form_tokens', $issuedTokens);

            $isHumanSubmission = '' === $form->get('company')->getData()
                && is_int($issuedAt)
                && $issuedAt <= $now - 3
                && !$contentFilter->isAdvertising($data['message'])
                && $submissionLimiter->allows($request->getClientIp() ?? 'unknown', $data['email']);

            if (!$isHumanSubmission) {
                $this->addFlash('success', 'Thanks—your request is on its way. I’ll reply with the best next step.');

                return $this->redirect($this->generateUrl('app_home').'#contact', Response::HTTP_SEE_OTHER);
            }

            $registerUrl = $this->generateUrl('app_register', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $mailer->send((new TemplatedEmail())
                ->from(new Address('pristine.web.dev@gmail.com', 'Pristine Web Consulting'))
                ->to('pristine.web.dev@gmail.com')
                ->replyTo($data['email'])
                ->subject(sprintf('Consultation request from %s', $data['name']))
                ->htmlTemplate('emails/consultation_request.html.twig')
                ->context([
                    'request' => $data,
                    'register_url' => $registerUrl,
                ]));

            $mailer->send((new TemplatedEmail())
                ->from(new Address('pristine.web.dev@gmail.com', 'Pristine Web Consulting'))
                ->to(new Address($data['email'], $data['name']))
                ->replyTo('pristine.web.dev@gmail.com')
                ->subject('Your consultation request — Pristine Web Consulting')
                ->htmlTemplate('emails/consultation_received.html.twig')
                ->context([
                    'name' => $data['name'],
                    'register_url' => $registerUrl,
                ]));

            $this->addFlash('success', 'Thanks—your request is on its way. I’ll reply with the best next step.');

            return $this->redirect($this->generateUrl('app_home').'#contact', Response::HTTP_SEE_OTHER);
        }

        return $this->render('home/index.html.twig', ['consultation_form' => $form]);
    }
}
