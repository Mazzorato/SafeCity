<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Localization\SupportedLocale;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Applique la langue du profil à toute l’interface après l’authentification.
 */
final readonly class UserLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        // Les pages anonymes conservent la langue choisie à l’inscription.
        $sessionLocale = $request->hasSession()
            ? $request->getSession()->get('_locale')
            : null;

        $locale = $user instanceof User
            ? SupportedLocale::normalize($user->getProfile()?->getLanguage())
            : SupportedLocale::normalize(is_string($sessionLocale) ? $sessionLocale : null);

        $request->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        // La priorité négative permet de lire l’utilisateur après le pare-feu.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -20],
        ];
    }
}