<?php

namespace App\Service;

use App\Entity\CoachApplication;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Psr\Log\LoggerInterface;

class CoachApplicationEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $fromEmail,
        private string $fromName,
        private string $projectDir   // ← AJOUT
    ) {}

    private function buildEmail(CoachApplication $application, string $template, string $subject): TemplatedEmail
    {
        $user = $application->getUser();
        $logoPath = $this->projectDir . '/public/assets/img/eyetwin-logo.png';

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context([
                'application'   => $application,
                'user'          => $user,
                'reviewComment' => $application->getReviewComment(),
            ]);

        // Attacher le logo en inline si le fichier existe
        if (file_exists($logoPath)) {
            $email->addPart(
                (new DataPart(fopen($logoPath, 'r'), 'logo', 'image/png'))->asInline()
            );
        }

        return $email;
    }

    public function sendApprovalEmail(CoachApplication $application): void
    {
        $user = $application->getUser();

        $this->logger->info('Attempting to send approval email', [
            'user_id'        => $user->getId(),
            'user_email'     => $user->getEmail(),
            'application_id' => $application->getId(),
        ]);

        try {
            $email = $this->buildEmail(
                $application,
                'emails/coach_application_approved.html.twig',
                '✅ Congratulations! Your Coach Application Has Been Approved'
            );

            $this->mailer->send($email);

            $this->logger->info('Approval email sent successfully', [
                'user_email'     => $user->getEmail(),
                'application_id' => $application->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error sending approval email', [
                'error'   => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);
            throw new \RuntimeException('Failed to send approval email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function sendRejectionEmail(CoachApplication $application): void
    {
        $user = $application->getUser();

        $this->logger->info('Attempting to send rejection email', [
            'user_id'        => $user->getId(),
            'user_email'     => $user->getEmail(),
            'application_id' => $application->getId(),
        ]);

        try {
            $email = $this->buildEmail(
                $application,
                'emails/coach_application_rejected.html.twig',
                '📋 Update on Your Coach Application — EyeTwin'
            );

            $this->mailer->send($email);

            $this->logger->info('Rejection email sent successfully', [
                'user_email'     => $user->getEmail(),
                'application_id' => $application->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error sending rejection email', [
                'error'   => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);
            throw new \RuntimeException('Failed to send rejection email: ' . $e->getMessage(), 0, $e);
        }
    }
}