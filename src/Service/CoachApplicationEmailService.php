<?php
// src/Service/CoachApplicationEmailService.php

namespace App\Service;

use App\Entity\CoachApplication;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class CoachApplicationEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $fromEmail,
        private string $fromName
    ) {
        $this->logger->info('CoachApplicationEmailService initialized', [
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
        ]);
    }

    public function sendApprovalEmail(CoachApplication $application): void
    {
        $user = $application->getUser();
        
        $this->logger->info('Attempting to send approval email', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'application_id' => $application->getId(),
        ]);
        
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject('✅ Congratulations! Your Coach Application Has Been Approved')
                ->htmlTemplate('emails/coach_application_approved.html.twig')
                ->context([
                    'application' => $application,
                    'user' => $user,
                    'reviewComment' => $application->getReviewComment(),
                ]);

            $this->mailer->send($email);
            
            $this->logger->info('Approval email sent successfully', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'application_id' => $application->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error sending approval email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
            ]);
            
            // Re-throw l'exception pour que l'admin sache qu'il y a eu un problème
            throw new \RuntimeException('Failed to send approval email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function sendRejectionEmail(CoachApplication $application): void
    {
        $user = $application->getUser();
        
        $this->logger->info('Attempting to send rejection email', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'application_id' => $application->getId(),
        ]);
        
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject('📋 Update on Your Coach Application')
                ->htmlTemplate('emails/coach_application_rejected.html.twig')
                ->context([
                    'application' => $application,
                    'user' => $user,
                    'reviewComment' => $application->getReviewComment(),
                ]);

            $this->mailer->send($email);
            
            $this->logger->info('Rejection email sent successfully', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'application_id' => $application->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error sending rejection email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
            ]);
            
            throw new \RuntimeException('Failed to send rejection email: ' . $e->getMessage(), 0, $e);
        }
    }
}