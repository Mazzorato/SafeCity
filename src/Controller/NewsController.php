<?php

namespace App\Controller;

use App\Entity\News;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NewsCategoryEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Présente les actualités locales et leur filtrage.
 */
final class NewsController extends AbstractController
{
    #[Route('/news', name: 'app_news')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $category = $request->query->get('category', 'all');
        if ($category !== 'all' && NewsCategoryEnum::tryFrom($category) === null) {
            $category = 'all';
        }

        $featured = null;
        $news = [];

        if ($city) {
            $featured = $em->getRepository(News::class)->findOneBy(
                ['city' => $city, 'isFeatured' => true],
                ['publishedAt' => 'DESC']
            );

            $criteria = ['city' => $city];
            if ($category != 'all') {
                $criteria['category'] = NewsCategoryEnum::from($category);
            }

            $news = $em->getRepository(News::class)->findBy($criteria, ['publishedAt' => 'DESC']);
        }

        // Le témoin visuel reflète uniquement les notifications non lues du
        // compte connecté, sans charger celles des autres utilisateurs.
        $unreadCount = $em->getRepository(Notification::class)->count([
            'recipient' => $user,
            'isRead' => false,
        ]);

        return $this->render('news/index.html.twig', [
            'featured'  => $featured,
            'news' => $news,
            'city' => $city,
            'category' => $category,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/news/all', name: 'app_news_all', methods: ['GET'])]
    public function all(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $city = $user->getCity();
        $category = $request->query->get('category', 'all');
        $search = trim($request->query->getString('query'));
        $sort = $request->query->get('sort', 'recent');

        if ($category !== 'all' && NewsCategoryEnum::tryFrom($category) === null) {
            $category = 'all';
        }
        if (!in_array($sort, ['recent', 'oldest'], true)) {
            $sort = 'recent';
        }

        $news = [];
        if ($city !== null) {
            $queryBuilder = $entityManager->getRepository(News::class)->createQueryBuilder('news')
                ->where('news.city = :city')
                ->setParameter('city', $city)
                ->orderBy('news.publishedAt', $sort === 'oldest' ? 'ASC' : 'DESC');

            if ($category !== 'all') {
                $queryBuilder
                    ->andWhere('news.category = :category')
                    ->setParameter('category', NewsCategoryEnum::from($category));
            }
            if ($search !== '') {
                $queryBuilder
                    ->andWhere('LOWER(news.title) LIKE :search OR LOWER(news.content) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower($search) . '%');
            }

            $news = $queryBuilder->getQuery()->getResult();
        }

        return $this->render('news/all.html.twig', [
            'news' => $news,
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
        ]);
    }
}


