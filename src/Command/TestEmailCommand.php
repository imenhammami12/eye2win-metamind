<?php
// src/Command/TestEmailCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test email sending configuration',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Email Configuration');

        $email = (new Email())
            ->from('hammami.imen200@gmail.com')
            ->to('hammami.imen200@gmail.com') // Changez par votre email de test
            ->subject('Test Email from Eye2Win Platform')
            ->html('
                <h1>Test Email</h1>
                <p>If you receive this email, your configuration is working correctly!</p>
                <p>Sent at: ' . date('Y-m-d H:i:s') . '</p>
            ');

        try {
            $this->mailer->send($email);
            $io->success('Email sent successfully! Check your inbox.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send email: ' . $e->getMessage());
            $io->note('Error details: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}