<?php

namespace App\Controller;

use App\Entity\RoutingRule;
use App\Form\RoutingRuleType;
use App\Repository\RoutingRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/routing')]
/**
 * Gère les règles qui orientent les signalements vers les services.
 */
final class RoutingRuleController extends AbstractController
{
    #[Route('', name: 'app_admin_routing', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        RoutingRuleRepository $repository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $schemaReady = true;
        try {
            $rules = $repository->findBy([], ['priority' => 'ASC', 'id' => 'ASC']);
        } catch (\Throwable) {
            $rules = [];
            $schemaReady = false;
        }

        $rule = new RoutingRule();
        $form = $this->createForm(RoutingRuleType::class, $rule);

        if ($schemaReady) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($rule);
                $entityManager->flush();
                $this->addFlash('success', $translator->trans('flash.routing_rule_created'));

                return $this->redirectToRoute('app_admin_routing');
            }
        }

        return $this->render('admin/routing/index.html.twig', [
            'rules' => $rules,
            'form' => $form,
            'schemaReady' => $schemaReady,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_routing_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        RoutingRule $rule,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(RoutingRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('flash.routing_rule_updated'));

            return $this->redirectToRoute('app_admin_routing');
        }

        return $this->render('admin/routing/edit.html.twig', [
            'rule' => $rule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_routing_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(
        RoutingRule $rule,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('routing_rule_toggle_' . $rule->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        $rule->setEnabled(!$rule->isEnabled());
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_routing');
    }
}