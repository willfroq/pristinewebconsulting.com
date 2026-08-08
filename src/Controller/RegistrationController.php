<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailConfirmationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailConfirmationService $emailConfirmation,
        LoggerInterface $logger,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (!is_string($plainPassword)) {
                throw new \LogicException('A valid password is required.');
            }
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $emailConfirmation->send($user);
            } catch (\Throwable $exception) {
                $logger->error('Account confirmation email could not be sent.', [
                    'user_id' => $user->getId(),
                    'exception' => $exception,
                ]);
                $entityManager->remove($user);
                $entityManager->flush();
                $this->addFlash('error', 'We could not send the confirmation email. Please try again.');

                return $this->redirectToRoute('app_register');
            }

            return $this->redirectToRoute('app_check_email');
        }

        $response = $form->isSubmitted()
            ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
            : null;

        return $this->render(
            'registration/register.html.twig',
            ['registrationForm' => $form],
            $response,
        );
    }

    #[Route('/register/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('registration/check_email.html.twig');
    }

    #[Route('/verify/email/{id<\d+>}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        int $id,
        Request $request,
        UriSigner $uriSigner,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        try {
            $uriSigner->verify($request);
        } catch (SignedUriException) {
            $this->addFlash('error', 'This confirmation link is invalid or has expired.');

            return $this->redirectToRoute('app_login');
        }

        $user = $users->find($id);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('The account no longer exists.');
        }

        if (!$user->isVerified()) {
            $user->markVerified();
            $entityManager->flush();
        }

        $security->login($user, 'form_login', 'main');
        $this->addFlash('success', 'Your email is confirmed. Your account is ready.');

        return $this->redirectToRoute('app_dashboard');
    }
}
