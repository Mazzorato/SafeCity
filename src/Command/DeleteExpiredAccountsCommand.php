<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;


#[AsCommand(name: 'app:delete-expired-accounts')]
#[AsCronTask('0 2 * * *')]

class DeleteExpiredAccountsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limite = new \DateTimeImmutable('-30 days');

        $users = $this->em->getRepository(User::class)->createQueryBuilder('u')
          ->where('u.deleteRequestedAt IS NOT NULL')
          ->andWhere('u.deleteRequestedAt < :limite')
          ->setParameter('limite', $limite)
          ->getQuery()
          ->getResult();

        foreach ($users as $user) {
            $this->em->remove($user);
        }

        $this->em->flush();

        $output->writeln(count($users) . 'compte supprimé définitivement.');

        return Command::SUCCESS;
    }
}