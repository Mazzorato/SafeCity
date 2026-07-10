<?php

namespace App\Controller;

use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsController extends AbstractController
{
    #[Route('/news', name: 'app_news')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $city = $user->getCity();

        $featured = null;
        $news = [];

        if ($city) {
            $featured = $em->getRepository(News::class)->findOneBy(
                ['city' => $city, 'isFeatured' => true],
                ['publishedAt' => 'DESC']
            );

            $news = $em->getRepository(News::class)->findBy(
                ['city' => $city],
                ['publishedAt' => 'DESC']
            );
        }
        return $this->render('news/index.html.twig', [
            'featured'  => $featured,
            'news' => $news,
            'city' => $city,
        ]);
    }
}
