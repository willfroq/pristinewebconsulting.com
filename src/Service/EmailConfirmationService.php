<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class EmailConfirmationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
        private string $fromEmail,
    ) {
    }

    public function send(User $user): void
    {
        $userId = $user->getId();
        if (null === $userId) {
            throw new \LogicException('The user must be stored before confirmation is sent.');
        }

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['id' => $userId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $verificationUrl = $this->uriSigner->sign(
            $verificationUrl,
            new \DateInterval('PT24H'),
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Pristine Dev'))
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject('Confirm your Pristine Dev account')
            ->htmlTemplate('emails/confirm_account.html.twig')
            ->context([
                'user' => $user,
                'verification_url' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }
}
