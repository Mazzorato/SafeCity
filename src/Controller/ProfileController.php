<?php

namespace App\Controller;

use App\Entity\Profile;
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

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        //Crée le profil automatiquement si il n'existe pas.
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

            //Détection automatique de la langue du navigateur
            $browserLanguage = substr(
                $request->getPreferredLanguage(['fr', 'en', 'es', 'pt', 'it', 'de', 'ja', 'ar', 'ru', 'tr', 'pl', 'nl']) ?? 'fr',
                0, 2
            );
            $profile->setLanguage($browserLanguage);

            $user->setProfile($profile);
            $em->persist($profile);
            $em->flush();
        }

        $userForm = $this->createForm(UserFormType::class, $user);
        $profileForm = $this->createForm(ProfileFormType::class, $profile);

        $userForm->handleRequest($request);
        $profileForm->handleRequest($request);

        if($userForm->isSubmitted() && $userForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Preferences mise à jour.');
            return $this->redirectToRoute('app_profile');
        }


        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'userForm' => $userForm,
            'profileForm' => $profileForm,
        ]);
    }

    #[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(EntityManagerInterface $em) : Response 
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $user->setAccountActive(false);
        $em->flush();
        
        return $this->redirectToRoute('app_logout');
    }
}
