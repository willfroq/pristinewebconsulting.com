<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\LessonBookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(LessonBookingRepository $bookings): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('dashboard/index.html.twig', [
            'proposals' => $bookings->proposedFor($user),
            'upcoming' => $bookings->upcomingFor($user),
            'declined' => $bookings->declinedFor($user),
        ]);
    }
}
