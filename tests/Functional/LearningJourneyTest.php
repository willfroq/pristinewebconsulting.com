<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\LessonBooking;
use App\Entity\LessonPayment;
use App\Entity\User;
use App\Repository\LessonBookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LearningJourneyTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testLandingPagePresentsTheMentoringOffer(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Make the next technical decision a confident one.');
        self::assertSelectorTextContains('#engagements', 'Architecture review');
        self::assertSelectorTextContains('#engagements', 'Performance audit');
        self::assertSelectorTextContains('main', 'Plain-English advice');
        self::assertSelectorNotExists('[data-testid="user-menu"]');
        self::assertSelectorNotExists('[data-testid="mobile-user-menu"]');
        self::assertSame('Technical Advisor for Websites & Web Apps | Pristine Web Consulting', $crawler->filter('title')->text());
        self::assertSelectorExists('meta[name="robots"][content^="index, follow"]');
        self::assertSelectorExists('link[rel="canonical"][href="https://pristinewebconsulting.com/"]');

        $structuredData = $crawler->filter('script[type="application/ld+json"]')->text();
        self::assertJson($structuredData);
        self::assertStringContainsString('"ProfessionalService"', $structuredData);
    }

    public function testConsultationHoneypotSubmissionDoesNotSendEmail(): void
    {
        $crawler = $this->client->request('GET', '/');
        $form = $crawler->selectButton('Request a consultation →')->form([
            'consultation_request[name]' => 'Spam Bot',
            'consultation_request[email]' => 'bot@example.com',
            'consultation_request[message]' => 'This submission should be silently discarded by the honeypot.',
            'consultation_request[company]' => 'Automated Marketing LLC',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/#contact');
        self::assertEmailCount(0);
    }

    public function testPrivatePagesAreExcludedFromSearchAndCrawlFilesReferenceTheHomepage(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
        self::assertSelectorNotExists('link[rel="canonical"]');

        $robots = file_get_contents(dirname(__DIR__, 2).'/public/robots.txt');
        self::assertIsString($robots);
        self::assertStringContainsString('Sitemap:', $robots);

        $sitemap = file_get_contents(dirname(__DIR__, 2).'/public/sitemap.xml');
        self::assertIsString($sitemap);
        self::assertStringContainsString('<loc>https://pristinewebconsulting.com/</loc>', $sitemap);
    }

    public function testAccountDetailsAndRelevantActionsOnlyRenderWhenLoggedIn(): void
    {
        $user = $this->createUser(false);
        $this->client->loginUser($user);
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav', 'My dashboard');
        self::assertSelectorTextContains('main', 'Request a consultation');

        $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testLearnerWithActivitySeesTheLearningProgressExperience(): void
    {
        $user = $this->createUser(true);
        $completedBooking = new LessonBooking($user, new \DateTimeImmutable('-7 days'), 'HTML & CSS');
        $completedBooking->approve();
        $this->entityManager->persist($completedBooking);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/lesson-payment');
        self::assertResponseRedirects('/dashboard');

        $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Welcome, Grace.');
        self::assertSelectorTextContains('main', 'Client portal');
        self::assertSelectorTextContains('nav', 'My dashboard');
    }

    public function testVisitorConfirmsTheirEmailBeforeAccountAccess(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Create my client account →')->form([
            'registration_form[name]' => 'Ada Lovelace',
            'registration_form[email]' => 'ada@example.com',
            'registration_form[plainPassword][first]' => 'a-secure-password',
            'registration_form[plainPassword][second]' => 'a-secure-password',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/register/check-email');
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'ada@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame('a-secure-password', $user->getPassword());
        self::assertFalse($user->isVerified());
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailSubjectContains($email, 'Confirm your Pristine Dev account');
        self::assertEmailAddressContains($email, 'To', 'ada@example.com');
        $html = $email->getHtmlBody();
        if (is_resource($html)) {
            $html = stream_get_contents($html);
        }
        self::assertIsString($html);
        self::assertMatchesRegularExpression('/Confirm my email/', $html);
        self::assertSame(1, preg_match('/href="([^"]+)"/', $html, $matches));
        $verificationUrl = $matches[1] ?? null;
        self::assertIsString($verificationUrl);
        $verificationUrl = html_entity_decode($verificationUrl, \ENT_QUOTES | \ENT_HTML5);

        $this->client->request('GET', $verificationUrl);

        self::assertResponseRedirects('/dashboard');
        $this->entityManager->clear();
        $storedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'ada@example.com']);
        self::assertInstanceOf(User::class, $storedUser);
        self::assertTrue($storedUser->isVerified());
    }

    public function testInvalidRegistrationRendersActionableValidationErrorsForTurbo(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('Create my client account →')->form([
            'registration_form[name]' => '',
            'registration_form[email]' => 'not-an-email',
            'registration_form[plainPassword][first]' => 'short',
            'registration_form[plainPassword][second]' => 'different',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('[data-testid="registration-errors"]', 'Please check the highlighted fields.');
        self::assertSelectorTextContains('form', 'This value should not be blank.');
        self::assertSelectorTextContains('form', 'This value is not a valid email address.');
        self::assertSelectorTextContains('form', 'The password fields must match.');
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'not-an-email']));
    }

    public function testUnverifiedAccountCannotLogIn(): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user = (new User())->setName('Pending Learner')->setEmail('pending@example.com');
        $user->setPassword($hasher->hashPassword($user, 'a-secure-password'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Log in to your dashboard')->form([
            '_username' => 'pending@example.com',
            '_password' => 'a-secure-password',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains(
            'main',
            'Please confirm your email address before logging in.',
        );
    }

    public function testBookingRequiresAPaidLessonCredit(): void
    {
        $user = $this->createUser(false);
        $this->client->loginUser($user);
        $this->client->request('GET', '/booking');

        self::assertResponseRedirects('/consulting-payment');
    }

    public function testLearnerCanSpendAPaidCreditToProposeALesson(): void
    {
        $user = $this->createUser(true);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/booking');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/booking/reserve"]')->first()->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $booking = $this->entityManager->getRepository(LessonBooking::class)->findOneBy(['student' => $user]);
        self::assertInstanceOf(LessonBooking::class, $booking);
        self::assertSame('Architecture and technical decisions', $booking->getTopic());
        self::assertSame('proposed', $booking->getStatus());
        self::assertSame('UTC', $booking->getStartsAt()->getTimezone()->getName());
        $storedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $storedUser);
        self::assertSame(0, $storedUser->getLessonCredits());

        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Awaiting approval');
        self::assertSelectorTextNotContains('main', 'Scheduled consultations');
    }

    public function testLearnerCanDescribeAnOtherLessonTopic(): void
    {
        $user = $this->createUser(true);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/booking');
        $form = $crawler->filter('form[action="/booking/reserve"]')->first()->form([
            'topic' => 'Other',
            'other_topic' => 'Testing a payment webhook',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $booking = $this->entityManager->getRepository(LessonBooking::class)->findOneBy(['student' => $user]);
        self::assertInstanceOf(LessonBooking::class, $booking);
        self::assertSame('Testing a payment webhook', $booking->getTopic());
    }

    public function testLearnerWithMultipleCreditsCanProposeMultipleLessonDates(): void
    {
        $user = $this->createUser(true);
        $user->grantLessonCredit();
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $firstBookingPage = $this->client->request('GET', '/booking');
        $this->client->submit($firstBookingPage->filter('form[action="/booking/reserve"]')->first()->form());
        self::assertResponseRedirects('/dashboard');

        $secondBookingPage = $this->client->request('GET', '/booking');
        $this->client->submit($secondBookingPage->filter('form[action="/booking/reserve"]')->first()->form());
        self::assertResponseRedirects('/dashboard');

        $bookings = $this->entityManager->getRepository(LessonBooking::class)->findBy(['student' => $user]);
        self::assertCount(2, $bookings);
        $this->entityManager->clear();
        $storedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $storedUser);
        self::assertSame(0, $storedUser->getLessonCredits());
        self::assertSame('proposed', $bookings[0]->getStatus());
        self::assertSame('proposed', $bookings[1]->getStatus());
    }

    public function testDashboardShowsAPendingConsultationToItsLearner(): void
    {
        $user = $this->createUser(false);
        $proposal = new LessonBooking(
            $user,
            new \DateTimeImmutable('2026-09-15 10:00:00', new \DateTimeZone('Europe/Amsterdam')),
            'Architecture review',
        );
        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Awaiting approval');
        self::assertSelectorTextContains('main', 'Architecture review');
        self::assertSelectorTextContains('main', 'Tuesday, 15 Sep 2026 · 10:00 CEST');
    }

    public function testDashboardRendersUtcLessonTimeInAmsterdamTime(): void
    {
        $user = $this->createUser(false);
        $amsterdamStart = new \DateTimeImmutable(
            '+2 days 18:00',
            new \DateTimeZone('Europe/Amsterdam'),
        );
        $booking = new LessonBooking($user, $amsterdamStart, 'Timezone boundaries');
        $booking->approve();
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        self::assertSame('UTC', $booking->getStartsAt()->getTimezone()->getName());
        self::assertNotSame('18:00', $booking->getStartsAt()->format('H:i'));

        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '18:00 '.$amsterdamStart->format('T'));
    }

    public function testBookedTimesUseAmsterdamDayBoundariesAcrossDaylightSavingTime(): void
    {
        $user = $this->createUser(false);
        $summerBooking = new LessonBooking(
            $user,
            new \DateTimeImmutable('2026-07-15 07:00:00', new \DateTimeZone('UTC')),
            'Summer lesson',
        );
        $winterBooking = new LessonBooking(
            $user,
            new \DateTimeImmutable('2026-01-15 08:00:00', new \DateTimeZone('UTC')),
            'Winter lesson',
        );
        $this->entityManager->persist($summerBooking);
        $this->entityManager->persist($winterBooking);
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(LessonBooking::class);
        self::assertInstanceOf(LessonBookingRepository::class, $repository);
        $amsterdam = new \DateTimeZone('Europe/Amsterdam');

        self::assertSame(
            ['09:00'],
            $repository->bookedTimesForDay(new \DateTimeImmutable('2026-07-15 00:00:00', $amsterdam)),
        );
        self::assertSame(
            ['09:00'],
            $repository->bookedTimesForDay(new \DateTimeImmutable('2026-01-15 00:00:00', $amsterdam)),
        );
    }

    public function testMentorApprovalAddsProposalToLearnerDashboard(): void
    {
        $learner = $this->createUser(false);
        $proposal = new LessonBooking(
            $learner,
            new \DateTimeImmutable('+2 days 10:00'),
            'Symfony & PHP',
        );
        $this->entityManager->persist($proposal);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $admin = (new User())
            ->setName('Mentor Admin')
            ->setEmail('mentor@example.com')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'a-secure-password'));
        $admin->markVerified();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/lesson-proposals');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Symfony & PHP');
        $form = $crawler->selectButton('Approve')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson-proposals');
        $this->entityManager->clear();
        $storedProposal = $this->entityManager->getRepository(LessonBooking::class)->find($proposal->getId());
        self::assertInstanceOf(LessonBooking::class, $storedProposal);
        self::assertSame('approved', $storedProposal->getStatus());

        $storedLearner = $this->entityManager->getRepository(User::class)->find($learner->getId());
        self::assertInstanceOf(User::class, $storedLearner);
        $this->client->loginUser($storedLearner);
        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Symfony & PHP');
        self::assertSelectorTextContains('main', 'Scheduled');
    }

    public function testAdminCanReachBothReviewQueuesFromTheNavigation(): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $admin = (new User())
            ->setName('Mentor Admin')
            ->setEmail('mentor-navigation@example.com')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'a-secure-password'));
        $admin->markVerified();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav a[href="/admin/lesson-proposals"]');
        self::assertSelectorExists('nav a[href="/admin/payments"]');
    }

    public function testLearnerCanOpenDirectPayPalLinkAndSubmitTransactionId(): void
    {
        $user = $this->createUser(false);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/consulting-payment?option=architecture-review');
        self::assertSelectorExists(
            'a[href="https://www.paypal.com/paypalme/pristinedev/250EUR"]',
        );
        $form = $crawler->selectButton('Submit payment for approval')->form([
            'paypal_reference' => '1AB23456CD789012E',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/lesson-payment');
        $payment = $this->entityManager->getRepository(LessonPayment::class)
            ->findOneBy(['paypalReference' => '1AB23456CD789012E']);
        self::assertInstanceOf(LessonPayment::class, $payment);
        self::assertSame('PENDING', $payment->getStatus());

        $this->client->followRedirect();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Client portal');
    }

    public function testLearnerCanSubmitAnotherPaymentWhileFirstIsAwaitingReview(): void
    {
        $user = $this->createUser(false);
        $this->client->loginUser($user);

        foreach (['1AB23456CD789012E', '2AB23456CD789012F'] as $reference) {
            $crawler = $this->client->request('GET', '/consulting-payment?option=architecture-review');
            $form = $crawler->filter('form[action="/lesson-payment/confirm"]')->form([
                'paypal_reference' => $reference,
            ]);
            $this->client->submit($form);
            self::assertResponseRedirects('/lesson-payment');
        }

        self::assertCount(2, $this->entityManager->getRepository(LessonPayment::class)->findBy(['student' => $user]));
    }

    public function testMentorApprovalOfManualPaymentGrantsOneLessonCredit(): void
    {
        $learner = $this->createUser(false);
        $payment = new LessonPayment($learner, '1AB23456CD789012E');
        $this->entityManager->persist($payment);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $admin = (new User())
            ->setName('Mentor Admin')
            ->setEmail('mentor-payments@example.com')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'a-secure-password'));
        $admin->markVerified();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/payments');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', '1AB23456CD789012E');
        $form = $crawler->selectButton('Approve payment')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/payments');
        $this->entityManager->clear();
        $storedLearner = $this->entityManager->getRepository(User::class)->find($learner->getId());
        self::assertInstanceOf(User::class, $storedLearner);
        self::assertSame(1, $storedLearner->getLessonCredits());
        $storedPayment = $this->entityManager->getRepository(LessonPayment::class)->find($payment->getId());
        self::assertInstanceOf(LessonPayment::class, $storedPayment);
        self::assertSame('APPROVED', $storedPayment->getStatus());
    }

    public function testManualPaymentCannotBeApprovedTwice(): void
    {
        $user = $this->createUser(false);
        $payment = new LessonPayment($user, '1AB23456CD789012E');

        self::assertTrue($payment->approve());
        $user->grantLessonCredit();
        self::assertFalse($payment->approve());
        self::assertSame(1, $user->getLessonCredits());
    }

    private function createUser(bool $hasLessonCredit): User
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user = (new User())->setName('Grace Hopper')->setEmail('grace@example.com');
        $user->setPassword($hasher->hashPassword($user, 'a-secure-password'));
        $user->markVerified();
        if ($hasLessonCredit) {
            $user->grantLessonCredit();
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
