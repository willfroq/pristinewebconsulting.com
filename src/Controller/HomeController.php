<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ConsultationRequestType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HomeController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/', name: 'app_home')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ConsultationRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, email: string, website: string|null, message: string} $data */
            $data = $form->getData();
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
