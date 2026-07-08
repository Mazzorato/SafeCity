<?php

namespace App\Controller;

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
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        // Création automatiquement si profile non existant
        $profile = $user->getProfile();

        if (!$profile) {
            $profile = new Profile();
            $profile->setEmergencyNotifications(true);
            $profile->setWeatherNotifications(true);
            $profile->setTransportNotifications(true);
            $profile->setEventNotifications(true);
            $profile->setMicrophoneAccess(false);
            $profile->setCameraAccess(false);
            $profile->setLocationAccess(false);

            $browserLanguage = substr(
                $request->getPreferredLanguage(['fr', 'en', 'es', 'pt', 'it', 'de', 'ja', 'ar', 'ru', 'tr', 'pl', 'nl']) ?? 'fr',
                0,
                2
            );

            $profile->setLanguage($browserLanguage);

            $user->setProfile($profile);
            $em->persist($profile);
        }

        // Formulaire Utilisateur
        $userForm = $this->createForm(UserFormType::class, $user);
        $userForm->handleRequest($request);

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Informations mises à jour.');

            return $this->redirectToRoute('app_profile');
        }

        // Formulaire Profile
        $profileForm = $this->createForm(ProfileFormType::class, $profile);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Préférences mises à jour.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'userForm' => $userForm->createView(),
            'profileForm' => $profileForm->createView(),
        ]);
    }

    #[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token invalide');
        }

        // On désactive et on programme la demande — SANS toucher email/nom.
        $user->setAccountActive(false);
        $user->setDeleteRequestedAt(new \DateTimeImmutable());
        $user->setEmail('deleted_' . $user->getId() . '@anonymous.local');
        $user->setFirstName('Deleted');
        $user->setLastName('User');

        // On révoque les permissions sensibles immédiatement (pas besoin d'attendre 30 jours pour ça).
        $profile = $user->getProfile();
        if ($profile) {
            $profile->setLocationAccess(false);
            $profile->setCameraAccess(false);
            $profile->setMicrophoneAccess(false);
        }

        $em->flush();

        return $this->redirectToRoute('app_logout');
    }

    
}
