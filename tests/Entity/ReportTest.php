<?php

namespace App\Tests\Entity;

use App\Entity\Comment;
use App\Entity\Photo;
use App\Entity\Report;
use PHPUnit\Framework\TestCase;

/**
 * Modèle Doctrine représentant les données persistées de ReportTest.
 */
final class ReportTest extends TestCase
{
    public function testLocationCanRemainEmpty(): void
    {
        $report = (new Report())
            ->setLatitude(null)
            ->setLongitude(null);

        self::assertNull($report->getLatitude());
        self::assertNull($report->getLongitude());
    }

    public function testAddingRelatedContentKeepsBothSidesSynchronized(): void
    {
        $report = new Report();
        $photo = new Photo();
        $comment = new Comment();

        $report->addPhoto($photo);
        $report->addComment($comment);

        self::assertTrue($report->getPhotos()->contains($photo));
        self::assertSame($report, $photo->getReport());
        self::assertTrue($report->getComments()->contains($comment));
        self::assertSame($report, $comment->getReport());
    }
}
