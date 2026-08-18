<?php

namespace App\Controller;

use App\Entity\User;

use App\Entity\Event;
use App\Enum\EventCategoryEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EventController extends AbstractController
{
#[Route('/event', name: 'app_event')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $category = $request->query->getString('category', 'all');
        $search = trim($request->query->getString('query'));

        if ($category !== 'all' && EventCategoryEnum::tryFrom($category) === null) {
            $category = 'all';
        }

        $events = [];
        if ($city !== null) {
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
            'favoriteIds' => $user->getFavoriteEvents()->map(
                static fn (Event $event): ?int => $event->getId()
            )->toArray(),
        ]);
    }

    #[Route('/events/{id}/favorite', name: 'app_event_favorite', methods: ['POST'])]
    public function toggleFavorite(Event $event, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('favorite' . $event->getId(), $request->request->get('_token'))){
            throw $this->createAccessDeniedException('Token invalide');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getFavoriteEvents()->contains($event)){
            $user->removeFavoriteEvent($event);
        } else {
            $user->addFavoriteEvent($event);
        }
        $em->flush();

        return $this->redirectToRoute('app_event', $request->query->all());
    }
}


