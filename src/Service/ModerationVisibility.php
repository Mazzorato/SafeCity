<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Photo;
use App\Entity\Report;
use App\Enum\ModerationTargetEnum;
use App\Repository\ModerationCaseRepository;
use Psr\Log\LoggerInterface;

/**
 * Centralise les règles qui rendent un contenu visible ou masqué.
 */
final class ModerationVisibility
{
    /**
     * @var array<string, array<int, true>>
     */
    private array $hiddenIds = [];

    public function __construct(
        private ModerationCaseRepository $moderationCases,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return Comment[]
     */
    public function visibleComments(Report $report): array
    {
        return array_values(array_filter(
            $report->getComments()->toArray(),
            fn (Comment $comment): bool => !$this->isHidden(ModerationTargetEnum::COMMENT, $comment->getId())
        ));
    }

    /**
     * @return Photo[]
     */
    public function visiblePhotos(Report $report): array
    {
        return array_values(array_filter(
            $report->getPhotos()->toArray(),
            fn (Photo $photo): bool => !$this->isHidden(ModerationTargetEnum::PHOTO, $photo->getId())
        ));
    }

    private function isHidden(ModerationTargetEnum $targetType, ?int $targetId): bool
    {
        if ($targetId === null) {
            return false;
        }

        $key = $targetType->value;
        if (!array_key_exists($key, $this->hiddenIds)) {
            try {
                $this->hiddenIds[$key] = array_fill_keys(
                    $this->moderationCases->findHiddenTargetIds($targetType),
                    true
                );
            } catch (\Throwable $exception) {
                $this->hiddenIds[$key] = [];
                $this->logger->warning('Filtrage de modération indisponible avant migration.', [
                    'exception' => $exception,
                ]);
            }
        }

        return isset($this->hiddenIds[$key][$targetId]);
    }
}
