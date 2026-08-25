<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Report;
use App\Entity\User;
use App\Enum\ReportStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $reportRepository = $entityManager->getRepository(Report::class);

        return $this->render('admin/index.html.twig', [
            'stats' => [
                'total' => $reportRepository->count(),
                'reported' => $reportRepository->count(['status' => ReportStatusEnum::REPORTED]),
                'inProgress' => $reportRepository->count(['status' => ReportStatusEnum::IN_PROGRESS]),
                'resolved' => $reportRepository->count(['status' => ReportStatusEnum::RESOLVED]),
                'users' => $entityManager->getRepository(User::class)->count(['accountActive' => true]),
            ],
            'reports' => $reportRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'unreadNotifications' => $entityManager->getRepository(Notification::class)->count(['isRead' => false]),
            'moderationPending' => 0,
            'moderationReady' => false,
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $search = trim($request->query->getString('query'));
        $queryBuilder = $entityManager->getRepository(User::class)->createQueryBuilder('user')
            ->orderBy('user.registrationDate', 'DESC');
        if ($search !== '') {
            $queryBuilder
                ->where('LOWER(user.firstName) LIKE :search OR LOWER(user.lastName) LIKE :search OR LOWER(user.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $this->render('admin/users.html.twig', [
            'users' => $queryBuilder->getQuery()->getResult(),
            'search' => $search,
        ]);
    }
}