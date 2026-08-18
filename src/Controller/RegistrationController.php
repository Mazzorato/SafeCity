<?php

namespace App\Controller;

use App\Localization\SupportedLocale;

use App\Entity\Profile;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $user->setRegistrationDate(new \DateTime());
            $user->setRole(\App\Enum\RoleEnum::ROLE_USER);
            $user->setAccountActive(true);
            $user->setCguAccepted($form->get('agreeTerms')->getData());

            $user->setProfile(
                (new Profile())
                    ->setEmergencyNotifications(true)
                    ->setWeatherNotifications(true)
                    ->setTransportNotifications(true)
                    ->setEventNotifications(true)
                    ->setMicrophoneAccess(false)
                    ->setCameraAccess(false)
                    ->setLocationAccess(false)
                    ->setLanguage('fr')
            );
            $entityManager->persist($user);
            $entityManager->flush();

            $language = SupportedLocale::normalize($user->getProfile()?->getLanguage());
            $this->sendConfirmationEmail($user, $translator, $language);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

#[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        $language = SupportedLocale::normalize($user->getProfile()?->getLanguage());
        $request->getSession()->set('_locale', $language);
        $this->addFlash('success', $translator->trans('flash.email_confirmed', locale: $language));

        return $this->redirectToRoute('app_login');
    }

#[Route('/verify/email/resend', name: 'app_resend_verification_email', methods: ['POST'])]
    public function resendVerificationEmail(
        Request $request,
        UserRepository $userRepository,
        TranslatorInterface $translator,
    ): Response
    {
        if (!$this->isCsrfTokenValid('resend_verification_email', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $session = $request->getSession();
        $now = time();
        $lastRequestAt = (int) $session->get('verification_email_requested_at', 0);

        // Une réponse identique est renvoyée dans tous les cas afin de ne pas
        // révéler si une adresse possède déjà un compte SafeCity.
        if ($now - $lastRequestAt >= 60) {
            $session->set('verification_email_requested_at', $now);
            $email = trim($request->request->getString('email'));
            $user = $email !== '' ? $userRepository->findOneBy(['email' => $email]) : null;

            if ($user instanceof User && !$user->isVerified() && $user->isAccountActive()) {
                $language = SupportedLocale::normalize($user->getProfile()?->getLanguage());
                $this->sendConfirmationEmail($user, $translator, $language);
            }
        }

        $this->addFlash('success', $translator->trans('flash.confirmation_email_requested'));

        return $this->redirectToRoute('app_login');
    }

private function sendConfirmationEmail(
        User $user,
        TranslatorInterface $translator,
        string $language,
    ): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address('admin@safecity.fr', 'SafeCity'))
                ->to((string) $user->getEmail())
                ->subject($translator->trans('email.confirm.subject', locale: $language))
                ->locale($language)
                ->htmlTemplate('registration/confirmation_email.html.twig'),
        );
    }
}