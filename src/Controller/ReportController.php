<?php

namespace App\Controller;

use App\Entity\ReportStatusHistory;

use Symfony\Contracts\Translation\TranslatorInterface;

use Symfony\Component\Form\FormError;

use Psr\Log\LoggerInterface;

use App\Enum\ReportStatusEnum;

use App\Entity\Photo;
use App\Service\FileUploader;
use App\Entity\Report;
use App\Form\ReportType;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/report')]
final class ReportController extends AbstractController
{
    #[Route(name: 'app_report_index', methods: ['GET'])]
    public function index(ReportRepository $reportRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('report/index.html.twig', [
            'reports' => $reportRepository->findAll(),
        ]);
    }

#[Route('/new', name: 'app_report_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        LoggerInterface $logger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        if ($user->getCity() === null) {
            $this->addFlash('warning', $this->translator->trans('flash.choose_city_first'));

            return $this->redirectToRoute('app_city_select');
        }

        $report = new Report();
        $report->setCity($user->getCity());

        $form = $this->createForm(ReportType::class, $report, [
            'report_creation' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $createdAt = new \DateTime();
            $report
                ->setReporter($user)
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setCreatedAt($createdAt);

            // La première étape est enregistrée avec la même date que l’envoi
            // afin que la chronologie soit exacte dès la création.
            $initialHistory = (new ReportStatusHistory())
                ->setStatus(ReportStatusEnum::REPORTED)
                ->setChangedAt(\DateTimeImmutable::createFromMutable($createdAt))
                ->setChangedBy($user);
            $report->addStatusHistory($initialHistory);

            $uploadedPhotoFilenames = [];

            try {
                foreach (['photo1', 'photo2', 'photo3'] as $field) {
                    $photoFile = $form->get($field)->getData();

                    if ($photoFile !== null) {
                        $uploadedPhotoFilenames[] = $fileUploader->upload($photoFile);
                    }
                }
            } catch (\Throwable $exception) {
                $this->cleanupFailedPhotoUploads(
                    $uploadedPhotoFilenames,
                    $fileUploader,
                    $logger,
                );
                $logger->error('Échec du téléversement des médias d’un signalement.', [
                    'user_id' => $user->getId(),
                    'exception' => $exception,
                ]);
                $form->addError(new FormError(
                    $this->translator->trans('flash.media_upload_failed')
                ));
                $response = $this->render('report/new.html.twig', [
                    'report' => $report,
                    'form' => $form,
                ]);
                $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

                return $response;
            }

            foreach ($uploadedPhotoFilenames as $photoFilename) {
                $photo = new Photo();
                $photo
                    ->setUrl('/uploads/photos/' . $photoFilename)
                    ->setUploadedAt(new \DateTime())
                    ->setUploader($user)
                    ->setReport($report);

                $entityManager->persist($photo);
            }

            $entityManager->persist($report);
            $entityManager->persist($initialHistory);

            try {
                $entityManager->flush();
            } catch (\Throwable $exception) {
                $this->cleanupFailedPhotoUploads(
                    $uploadedPhotoFilenames,
                    $fileUploader,
                    $logger,
                );
                $logger->error('Échec de l’enregistrement en base d’un signalement.', [
                    'user_id' => $user->getId(),
                    'exception' => $exception,
                ]);
                $this->addFlash(
                    'error',
                    $this->translator->trans('flash.report_save_failed')
                );

                return $this->redirectToRoute('app_report_new');
            }

            $this->addFlash('success', $this->translator->trans('flash.report_sent'));

            return $this->redirectToRoute('app_report_show', [
                'id' => $report->getId(),
            ]);
        }

        return $this->render('report/new.html.twig', [
            'report' => $report,
            'form' => $form,
        ]);
    }

#[Route('/my-reports', name: 'app_report_my_reports', methods: ['GET'])]
    public function myReports(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $status = $this->validatedStatus($request->query->getString('status', 'all'));
        $sort = $request->query->getString('sort', 'recent');
        if (!in_array($sort, ['recent', 'oldest'], true)) {
            $sort = 'recent';
        }

        $allReports = $entityManager->getRepository(Report::class)->createQueryBuilder('report')
            ->where('report.reporter = :user')
            ->setParameter('user', $user)
            ->orderBy('report.createdAt', $sort === 'oldest' ? 'ASC' : 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('report/my_reports.html.twig', [
            'reports' => $this->filterReportsByStatus($allReports, $status),
            'stats' => $this->reportStats($allReports),
            'status' => $status,
            'sort' => $sort,
        ]);
    }

#[Route('/{id}/follow-up', name: 'app_report_follow_up', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function followUp(Report $report): Response
    {
        $this->denyReportOwnerOrAdmin($report);

        return $this->render('report/follow_up.html.twig', [
            'report' => $report,
        ]);
    }

#[Route('/all', name: 'app_report_all', methods: ['GET'])]
    public function all(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $status = $this->validatedStatus($request->query->getString('status', 'all'));
        $search = trim($request->query->getString('query'));
        $sort = $request->query->getString('sort', 'recent');
        if (!in_array($sort, ['recent', 'oldest'], true)) {
            $sort = 'recent';
        }

        $allReports = [];
        if ($city !== null) {
            $queryBuilder = $entityManager->getRepository(Report::class)->createQueryBuilder('report')
                ->where('report.city = :city')
                ->setParameter('city', $city)
                ->orderBy('report.createdAt', $sort === 'oldest' ? 'ASC' : 'DESC');

            if ($search !== '') {
                $queryBuilder
                    ->andWhere('(LOWER(report.address) LIKE :search OR LOWER(report.description) LIKE :search)')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            $allReports = $queryBuilder->getQuery()->getResult();
        }

        return $this->render('report/all.html.twig', [
            'reports' => $this->filterReportsByStatus($allReports, $status),
            'stats' => $this->reportStats($allReports),
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'city' => $city,
        ]);
    }

    #[Route('/{id}', name: 'app_report_show', methods: ['GET'])]
    public function show(Report $report): Response
    {
        return $this->render('report/show.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_report_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Report $report, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_report_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('report/edit.html.twig', [
            'report' => $report,
            'form' => $form,
        ]);
    }

#[Route('/{id}/delete', name: 'app_report_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Report $report,
        EntityManagerInterface $entityManager,
        FileUploader $fileUploader,
        LoggerInterface $logger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $report->getId(), $request->getPayload()->getString('_token'))) {
            $photoFilenames = [];
            foreach ($report->getPhotos() as $photo) {
                $url = $photo->getUrl();
                if ($url !== null && str_starts_with($url, '/uploads/photos/')) {
                    // Seuls les médias gérés par le stockage local SafeCity sont
                    // concernés ; une éventuelle URL externe n’est jamais suivie.
                    $photoFilenames[] = basename($url);
                }
            }

            $entityManager->remove($report);
            $entityManager->flush();

            // La base est supprimée avant les fichiers : une erreur Doctrine ne
            // peut donc pas laisser un signalement actif avec une image absente.
            foreach (array_unique($photoFilenames) as $photoFilename) {
                try {
                    $fileUploader->remove($photoFilename);
                } catch (\Throwable $exception) {
                    $logger->warning('Nettoyage impossible après la suppression administrative d’un signalement.', [
                        'report_id' => $report->getId(),
                        'filename' => $photoFilename,
                        'exception' => $exception,
                    ]);
                }
            }
        }

        return $this->redirectToRoute('app_report_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/map/view', name: 'app_report_map', methods: ['GET'])]
    public function map(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city =$user->getCity();

        $category = $request->query->get('category', 'all');
        $groups = [
            'accidents' => ['accident', 'route'],
            'travaux' => ['travaux'],
            'urgences' => ['incendie', 'sante'],
        ];

        $reports = [];

        if ($city) {
            $allReports = $entityManager->getRepository(Report::class)->findBy(
                ['city' => $city],
                ['createdAt' => 'DESC']
            );

            $reports = $category === 'all'
                ? $allReports
                : array_filter($allReports, fn(Report $r) => in_array($r->getCategory()->getIcon(), $groups[$category] ?? []));
        }

        return $this->render('report/map.html.twig', [
            'reports' => $reports,
            'city' => $city,
            'category' => $category,
        ]);
    }

public function __construct(private TranslatorInterface $translator)
    {
    }

private function cleanupFailedPhotoUploads(
        array $photoFilenames,
        FileUploader $fileUploader,
        LoggerInterface $logger,
    ): void {
        foreach ($photoFilenames as $photoFilename) {
            try {
                $fileUploader->remove($photoFilename);
            } catch (\Throwable $exception) {
                $logger->warning('Nettoyage impossible d’une photo incomplète.', [
                    'filename' => $photoFilename,
                    'exception' => $exception,
                ]);
            }
        }
    }

private function validatedStatus(string $status): string
    {
        $allowedStatuses = ['all', ...array_map(
            static fn (ReportStatusEnum $reportStatus): string => $reportStatus->value,
            ReportStatusEnum::cases()
        )];

        return in_array($status, $allowedStatuses, true) ? $status : 'all';
    }

private function filterReportsByStatus(array $reports, string $status): array
    {
        if ($status === 'all') {
            return $reports;
        }

        return array_values(array_filter(
            $reports,
            static fn (Report $report): bool => $report->getStatus()?->value === $status
        ));
    }

private function reportStats(array $reports): array
    {
        $stats = ['total' => count($reports), 'reported' => 0, 'inProgress' => 0, 'resolved' => 0];

        foreach ($reports as $report) {
            match ($report->getStatus()) {
                ReportStatusEnum::REPORTED => ++$stats['reported'],
                ReportStatusEnum::IN_PROGRESS => ++$stats['inProgress'],
                ReportStatusEnum::RESOLVED => ++$stats['resolved'],
                default => null,
            };
        }

        return $stats;
    }

private function denyReportOwnerOrAdmin(Report $report): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($report->getReporter()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException($this->translator->trans('security.report_not_owned'));
        }
    }
}
