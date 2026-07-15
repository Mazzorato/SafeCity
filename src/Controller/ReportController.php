<?php

namespace App\Controller;

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
    FileUploader $fileUploader
): Response
{
    $this->denyAccessUnlessGranted('ROLE_USER');

    $report = new Report();
    $form = $this->createForm(ReportType::class, $report);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $report->setReporter($this->getUser());
        $report->setStatus(\App\Enum\ReportStatusEnum::REPORTED);
        $report->setCreatedAt(new \DateTime());

        // Enregistrement des 3 photos
        foreach (['photo1', 'photo2', 'photo3'] as $field) {

            $photoFile = $form->get($field)->getData();

            if ($photoFile === null) {
                continue;
            }

            $filename = $fileUploader->upload($photoFile);

            $photo = new Photo();
            $photo->setUrl('/uploads/photos/' . $filename);
            $photo->setUploadedAt(new \DateTime());
            $photo->setUploader($this->getUser());
            $photo->setReport($report);

            $entityManager->persist($photo);
        }

        $entityManager->persist($report);
        $entityManager->flush();

        $this->addFlash('success', 'Votre signalement a bien été envoyé.');

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
public function myReports(): Response
{
    $this->denyAccessUnlessGranted('ROLE_USER');

    /** @var \App\Entity\User $user */
    $user = $this->getUser();

    return $this->render('report/my_reports.html.twig', [
        'reports' => $user->getReports(),
    ]);
}

    #[Route('/{id}/follow-up', name: 'app_report_follow_up', methods: ['GET', 'POST'])]
    public function followUp(Report $report): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('report/follow_up.html.twig', [
            'report' => $report,
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

    #[Route('/{id}', name: 'app_report_delete', methods: ['POST'])]
    public function delete(Request $request, Report $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$report->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($report);
            $entityManager->flush();
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
}
