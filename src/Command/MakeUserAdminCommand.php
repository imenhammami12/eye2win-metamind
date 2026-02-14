<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:make-admin',
    description: 'Ajoute le rôle ROLE_ADMIN à un utilisateur',
)]
class MakeUserAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("Utilisateur avec l'email '{$email}' introuvable.");
            return Command::FAILURE;
        }

        $io->title("Utilisateur trouvé : {$user->getUsername()} ({$user->getEmail()})");
        
        $currentRoles = $user->getRoles();
        $io->section('Rôles actuels :');
        $io->listing($currentRoles);

        // Vérifier si déjà admin
        if (in_array('ROLE_ADMIN', $currentRoles)) {
            $io->success('Cet utilisateur est déjà administrateur !');
            return Command::SUCCESS;
        }

        // Ajouter ROLE_ADMIN
        $newRoles = $currentRoles;
        $newRoles[] = 'ROLE_ADMIN';
        $user->setRoles(array_unique($newRoles));

        $this->em->flush();

        $io->section('Nouveaux rôles :');
        $io->listing($user->getRoles());
        $io->success("ROLE_ADMIN ajouté avec succès à {$user->getEmail()} !");

        // Afficher info sur la reconnaissance faciale
        if ($user->getFaceDescriptor()) {
            $io->note('✓ Cet utilisateur a la reconnaissance faciale activée.');
        } else {
            $io->warning('⚠ Cet utilisateur n\'a PAS de reconnaissance faciale configurée.');
        }

        return Command::SUCCESS;
    }
}
