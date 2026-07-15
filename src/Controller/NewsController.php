<?php

namespace App\Controller;

use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route('/news', name: 'app_news')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $category = $request->query->get('category', 'all');

        $featured = null;
        $news = [];

        if ($city) {
            $featured = $em->getRepository(News::class)->findOneBy(
                ['city' => $city, 'isFeatured' => true],
                ['publishedAt' => 'DESC']
            );

            $criteria = ['city' => $city];
            if ($category != 'all') {
                $criteria['category'] = \App\Enum\NewsCategoryEnum::from($category);
            }

            $news = $em->getRepository(News::class)->findBy($criteria, ['publishedAt' => 'DESC']);
        }
        return $this->render('news/index.html.twig', [
            'featured'  => $featured,
            'news' => $news,
            'city' => $city,
            'category' => $category,
        ]);
    }
}
