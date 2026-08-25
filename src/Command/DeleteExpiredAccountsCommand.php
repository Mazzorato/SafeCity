<?php

namespace App\Command;

use App\Entity\Comment;
use App\Entity\Notification;
use App\Entity\Photo;
use App\Entity\Report;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Enum\ModerationTargetEnum;
use App\Repository\ModerationCaseRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;


#[AsCommand(
    name: 'app:delete-expired-accounts',
    description: 'Supprime les comptes désactivés depuis plus de trente jours et leurs données personnelles.',
)]
#[AsCronTask('0 2 * * *')]

/**
 * Supprime les comptes dont le délai de suppression différée est arrivé à échéance.
 */
final class DeleteExpiredAccountsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploader $fileUploader,
        private readonly ModerationCaseRepository $moderationCases,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Une exécution interrompue est réparée avant de sélectionner de
        // nouveaux comptes : une photo référencée revient en ligne, sinon elle
        // termine sa suppression différée.
        if (!$this->recoverInterruptedQuarantine($output)) {
            return Command::FAILURE;
        }

        $limit = new \DateTimeImmutable('-30 days');

        $users = $this->entityManager->getRepository(User::class)->createQueryBuilder('user')
            ->where('user.accountActive = :inactive')
            ->andWhere('user.deleteRequestedAt IS NOT NULL')
            ->andWhere('user.deleteRequestedAt <= :limit')
            ->setParameter('inactive', false)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();

        $photoFilenames = [];
        $commentIds = [];
        $photoIds = [];
        $deletionPlans = [];

        foreach ($users as $user) {
            $reports = $this->entityManager->getRepository(Report::class)->findBy(['reporter' => $user]);
            $comments = $this->entityManager->getRepository(Comment::class)->createQueryBuilder('comment')
                ->leftJoin('comment.report', 'report')
                ->andWhere('comment.author = :user OR report.reporter = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();
            $photos = $this->entityManager->getRepository(Photo::class)->createQueryBuilder('photo')
                ->leftJoin('photo.report', 'report')
                ->andWhere('photo.uploader = :user OR report.reporter = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getResult();

            // Toutes les cibles supprimées sont mémorisées avant le flush afin
            // de nettoyer aussi leur dossier de modération sans référence SQL.
            foreach ($comments as $comment) {
                if ($comment->getId() !== null) {
                    $commentIds[] = $comment->getId();
                }
            }
            foreach ($photos as $photo) {
                if ($photo->getId() !== null) {
                    $photoIds[] = $photo->getId();
                }
                $url = $photo->getUrl();
                if ($url !== null && str_starts_with($url, '/uploads/photos/')) {
                    // Une URL externe éventuelle ne doit jamais déclencher la
                    // suppression d’un fichier local portant le même nom.
                    $photoFilenames[basename($url)] = true;
                }
            }

            $deletionPlans[] = [
                'user' => $user,
                'reports' => $reports,
                'comments' => $comments,
                'photos' => $photos,
                'notifications' => $this->entityManager
                    ->getRepository(Notification::class)
                    ->findBy(['recipient' => $user]),
                'resetRequests' => $this->entityManager
                    ->getRepository(ResetPasswordRequest::class)
                    ->findBy(['user' => $user]),
            ];
        }

        $quarantinedFilenames = [];
        try {
            foreach (array_keys($photoFilenames) as $filename) {
                if ($this->fileUploader->quarantine($filename)) {
                    $quarantinedFilenames[] = $filename;
                }
            }
        } catch (\Throwable $exception) {
            $this->restoreQuarantinedFiles($quarantinedFilenames, $output);
            $output->writeln(sprintf(
                '<error>La préparation des photos a échoué : %s</error>',
                $exception->getMessage(),
            ));

            return Command::FAILURE;
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->moderationCases->deleteForTargets(ModerationTargetEnum::COMMENT, $commentIds);
            $this->moderationCases->deleteForTargets(ModerationTargetEnum::PHOTO, $photoIds);

            foreach ($deletionPlans as $plan) {
                // Les suppressions explicites ne dépendent pas de l’état chargé
                // des collections inverses Doctrine de l’utilisateur.
                foreach ($plan['photos'] as $photo) {
                    $this->entityManager->remove($photo);
                }
                foreach ($plan['comments'] as $comment) {
                    $this->entityManager->remove($comment);
                }
                foreach ($plan['reports'] as $report) {
                    $this->entityManager->remove($report);
                }
                foreach ($plan['notifications'] as $notification) {
                    $this->entityManager->remove($notification);
                }
                foreach ($plan['resetRequests'] as $resetRequest) {
                    $this->entityManager->remove($resetRequest);
                }

                $this->entityManager->remove($plan['user']);
            }

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            // Le UnitOfWork ne doit conserver aucune suppression planifiée
            // après le retour arrière de la transaction SQL.
            $this->entityManager->clear();
            $this->restoreQuarantinedFiles($quarantinedFilenames, $output);
            $output->writeln(sprintf(
                '<error>La suppression des comptes a été annulée : %s</error>',
                $exception->getMessage(),
            ));

            return Command::FAILURE;
        }

        $cleanupFailures = 0;
        foreach ($quarantinedFilenames as $filename) {
            try {
                $this->fileUploader->removeQuarantined($filename);
            } catch (\Throwable $exception) {
                ++$cleanupFailures;
                $output->writeln(sprintf(
                    '<error>Le fichier en quarantaine « %s » n’a pas pu être supprimé : %s</error>',
                    $filename,
                    $exception->getMessage(),
                ));
            }
        }

        $deletedCount = count($users);
        $plural = $deletedCount === 1 ? '' : 's';
        $output->writeln(sprintf(
            '%d compte%s supprimé%s définitivement.',
            $deletedCount,
            $plural,
            $plural,
        ));

        return $cleanupFailures === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function recoverInterruptedQuarantine(OutputInterface $output): bool
    {
        $failures = 0;
        foreach ($this->fileUploader->quarantinedFilenames() as $filename) {
            $isReferenced = $this->entityManager->getRepository(Photo::class)->count([
                'url' => '/uploads/photos/' . $filename,
            ]) > 0;

            try {
                if ($isReferenced) {
                    $this->fileUploader->restoreQuarantined($filename);
                    $output->writeln(sprintf('Photo « %s » restaurée après une interruption.', $filename));
                } else {
                    $this->fileUploader->removeQuarantined($filename);
                    $output->writeln(sprintf('Photo orpheline « %s » supprimée de la quarantaine.', $filename));
                }
            } catch (\Throwable $exception) {
                ++$failures;
                $output->writeln(sprintf(
                    '<error>La récupération de « %s » a échoué : %s</error>',
                    $filename,
                    $exception->getMessage(),
                ));
            }
        }

        return $failures === 0;
    }

    /**
     * @param string[] $filenames
     */
    private function restoreQuarantinedFiles(array $filenames, OutputInterface $output): bool
    {
        $failures = 0;
        foreach (array_reverse($filenames) as $filename) {
            try {
                $this->fileUploader->restoreQuarantined($filename);
            } catch (\Throwable $exception) {
                ++$failures;
                $output->writeln(sprintf(
                    '<error>La restauration de « %s » a échoué : %s</error>',
                    $filename,
                    $exception->getMessage(),
                ));
            }
        }

        return $failures === 0;
    }
}