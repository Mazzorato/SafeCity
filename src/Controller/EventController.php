<?php

namespace App\Controller;

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

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $category = $request->query->get('category', 'all');

        $events = [];

        if ($city) {
            $criteria = ['city' => $city];
            if ($category !== 'all'){
                $criteria['category'] = EventCategoryEnum::from($category);
            }

            $events = $em->getRepository(Event::class)->findBy($criteria, ['startedAt' => 'ASC']);

        }
        return $this->render('event/index.html.twig', [
            'events' => $events,
            'city' => $city,
            'category' => $category,
            'favoriteIds' => $user->getFavoriteEvents()->map(fn(Event $e) => $e->getId())->toArray(), 
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
