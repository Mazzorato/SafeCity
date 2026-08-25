<?php

namespace App\Tests\Service;

use App\Entity\Comment;
use App\Entity\Photo;
use App\Entity\Report;
use App\Enum\ModerationTargetEnum;
use App\Repository\ModerationCaseRepository;
use App\Service\ModerationVisibility;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Vérifie le comportement couvert par ModerationVisibility.
 */
final class ModerationVisibilityTest extends TestCase
{
    public function testItFiltersOnlyHiddenCommentsAndPhotos(): void
    {
        $visibleComment = new Comment();
        $hiddenComment = new Comment();
        $visiblePhoto = new Photo();
        $hiddenPhoto = new Photo();

        self::setEntityId($visibleComment, 1);
        self::setEntityId($hiddenComment, 2);
        self::setEntityId($visiblePhoto, 3);
        self::setEntityId($hiddenPhoto, 4);

        $report = (new Report())
            ->addComment($visibleComment)
            ->addComment($hiddenComment)
            ->addPhoto($visiblePhoto)
            ->addPhoto($hiddenPhoto);

        $repository = $this->createMock(ModerationCaseRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('findHiddenTargetIds')
            ->willReturnCallback(static fn (ModerationTargetEnum $target): array => match ($target) {
                ModerationTargetEnum::COMMENT => [2],
                ModerationTargetEnum::PHOTO => [4],
            });

        $visibility = new ModerationVisibility($repository, $this->createStub(LoggerInterface::class));

        self::assertSame([$visibleComment], $visibility->visibleComments($report));
        self::assertSame([$visiblePhoto], $visibility->visiblePhotos($report));
    }

    private static function setEntityId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}