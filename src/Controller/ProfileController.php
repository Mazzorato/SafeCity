<?php

namespace App\Controller;

use Symfony\Contracts\Translation\TranslatorInterface;

use App\Localization\SupportedLocale;

use App\Entity\Profile;
use App\Entity\User;
use App\Form\ProfileFormType;
use App\Form\UserFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
#[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $profile = $user->getProfile();

        if ($profile === null) {
            $profile = $this->createDefaultProfile($request);
            $user->setProfile($profile);
            $entityManager->persist($profile);
        }

        $userForm = $this->createForm(UserFormType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('flash.profile_updated'));

            return $this->redirectToRoute('app_profile');
        }

        $profileForm = $this->createForm(ProfileFormType::class, $profile);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $profile->setLanguage(SupportedLocale::normalize($profile->getLanguage()));
            $entityManager->flush();
            // Le prochain écran, y compris après déconnexion, reprend le choix.
            $request->getSession()->set('_locale', $profile->getLanguage());
            $this->addFlash('success', $translator->trans('flash.preferences_updated'));

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'userForm' => $userForm,
            'profileForm' => $profileForm,
        ]);
    }

#[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Request $request,
        TranslatorInterface $translator,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('delete_account', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        /** @var User $user */
        $user = $this->getUser();
        $user
            ->setAccountActive(false)
            ->setDeleteRequestedAt(new \DateTimeImmutable())
            ->setEmail('deleted_' . $user->getId() . '@anonymous.local')
            ->setFirstName('Compte')
            ->setLastName('Désactivé');

        $profile = $user->getProfile();
        if ($profile !== null) {
            $profile
                ->setLocationAccess(false)
                ->setCameraAccess(false);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_logout');
    }

    

private function createDefaultProfile(Request $request): Profile
    {
        $profile = new Profile();

        return $profile
            ->setEmergencyNotifications(true)
            ->setTransportNotifications(true)
            ->setEventNotifications(true)
            ->setCameraAccess(false)
            ->setLocationAccess(false)
            ->setLanguage(SupportedLocale::DEFAULT);
    }

#[Route('/delete/confirm', name: 'app_profile_delete_confirm', methods: ['GET'])]
    public function confirmDelete(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('profile/delete_confirm.html.twig');
    }
}
