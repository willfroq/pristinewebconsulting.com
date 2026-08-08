<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:make-admin',
    description: 'Give an existing verified account access to lesson proposals.',
)]
final class MakeAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Existing account email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        if (!is_string($email) || '' === $email) {
            $io->error('Provide a valid account email.');

            return Command::INVALID;
        }

        $user = $this->users->loadUserByIdentifier($email);
        if (null === $user) {
            $io->error('No account was found for that email.');

            return Command::FAILURE;
        }
        if (!$user->isVerified()) {
            $io->error('Confirm the account email before making it an administrator.');

            return Command::FAILURE;
        }

        $user->setRoles([...$user->getRoles(), 'ROLE_ADMIN']);
        $this->entityManager->flush();
        $io->success(sprintf('%s can now review lesson proposals.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
