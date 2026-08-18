<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventFavorite;
use App\Entity\User;
use App\Enum\EventCategoryEnum;
use App\Repository\EventFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gère la liste des événements et les favoris de l’utilisateur.
 */
final class EventController extends AbstractController
{
    #[Route('/event', name: 'app_event')]
    public function index(
        EntityManagerInterface $em,
        EventFavoriteRepository $favoriteRepository,
        Request $request,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $category = $request->query->get('category', 'all');
        $search = trim($request->query->getString('query'));

        if ($category !== 'all' && EventCategoryEnum::tryFrom($category) === null) {
            $category = 'all';
        }

        $events = [];

        if ($city) {
            $queryBuilder = $em->getRepository(Event::class)->createQueryBuilder('event')
                ->where('event.city = :city')
                ->andWhere('event.startedAt >= :now')
                ->setParameter('city', $city)
                ->setParameter('now', new \DateTime())
                ->orderBy('event.startedAt', 'ASC');

            if ($category !== 'all') {
                $queryBuilder
                    ->andWhere('event.category = :category')
                    ->setParameter('category', EventCategoryEnum::from($category));
            }
            if ($search !== '') {
                $queryBuilder
                    ->andWhere('LOWER(event.title) LIKE :search OR LOWER(event.location) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            $events = $queryBuilder->getQuery()->getResult();
        }
        return $this->render('event/index.html.twig', [
            'events' => $events,
            'city' => $city,
            'category' => $category,
            'search' => $search,
            'favoriteIds' => $favoriteRepository->findEventIdsForUser($user),
        ]);
    }

    #[Route('/events/favorites', name: 'app_event_favorites', methods: ['GET'])]
    public function favorites(EventFavoriteRepository $favoriteRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('event/favorites.html.twig', [
            'favorites' => $favoriteRepository->findForUser($user),
        ]);
    }

    #[Route('/events/favorites/clear', name: 'app_event_favorites_clear', methods: ['POST'])]
    public function clearFavorites(
        EntityManagerInterface $entityManager,
        EventFavoriteRepository $favoriteRepository,
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('clear_event_favorites', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        /** @var User $user */
        $user = $this->getUser();
        foreach ($favoriteRepository->findForUser($user) as $favorite) {
            $entityManager->remove($favorite);
        }
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('flash.event_favorites_cleared'));

        return $this->redirectToRoute('app_event_favorites');
    }

    #[Route('/events/{id}/favorite', name: 'app_event_favorite', methods: ['POST'])]
    public function toggleFavorite(
        Event $event,
        EntityManagerInterface $entityManager,
        EventFavoriteRepository $favoriteRepository,
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('favorite' . $event->getId(), $request->request->get('_token'))){
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($user->getCity()?->getId() !== $event->getCity()?->getId()) {
            // Une URL forgée ne doit jamais permettre d’ajouter au profil un
            // événement qui n’appartient pas à la ville courante.
            throw $this->createAccessDeniedException($translator->trans('security.content_wrong_city'));
        }

        $favorite = $favoriteRepository->findOneForUserAndEvent($user, $event);
        if ($favorite !== null) {
            $entityManager->remove($favorite);
            $this->addFlash('success', $translator->trans('flash.event_favorite_removed'));
        } else {
            // Tout nouveau favori active son rappel conformément au parcours
            // annoncé, l’utilisateur pouvant ensuite le désactiver.
            $favorite = (new EventFavorite())
                ->setEventUser($user)
                ->setEvent($event)
                ->setAddedAt(new \DateTime())
                ->setReminderActive(true);
            $entityManager->persist($favorite);
            $this->addFlash('success', $translator->trans('flash.event_favorite_added'));
        }
        $entityManager->flush();

        return $request->request->get('_target') === 'favorites'
            ? $this->redirectToRoute('app_event_favorites')
            : $this->redirectToRoute('app_event', $request->query->all());
    }

    #[Route(
        '/events/favorites/{id}/reminder',
        name: 'app_event_favorite_reminder',
        methods: ['POST'],
        requirements: ['id' => '\d+']
    )]
    public function toggleReminder(
        EventFavorite $favorite,
        EntityManagerInterface $entityManager,
        Request $request,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        if ($favorite->getEventUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException($translator->trans('security.favorite_not_owned'));
        }

        if (!$this->isCsrfTokenValid(
            'favorite_reminder_' . $favorite->getId(),
            $request->getPayload()->getString('_token')
        )) {
            throw $this->createAccessDeniedException($translator->trans('security.invalid_token'));
        }

        $favorite->setReminderActive(!$favorite->isReminderActive());
        $entityManager->flush();

        $this->addFlash(
            'success',
            $favorite->isReminderActive()
                ? $translator->trans('flash.event_reminder_enabled')
                : $translator->trans('flash.event_reminder_disabled')
        );

        return $this->redirectToRoute('app_event_favorites');
    }
}
