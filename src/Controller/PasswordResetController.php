<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PasswordResetToken;
use App\Form\PasswordResetRequestType;
use App\Form\PasswordResetType;
use App\Service\MultiChannelNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private MultiChannelNotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        MultiChannelNotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->notificationService = $notificationService;
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(Request $request): Response
    {
        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $channel = $form->get('notificationChannel')->getData();

            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if ($user) {
                // Generate reset token
                $token = $this->generateResetToken();
                $resetToken = new PasswordResetToken();
                $resetToken->setUser($user);
                $resetToken->setToken($token);
                $resetToken->setExpiresAt(new \DateTime('+1 hour'));
                $resetToken->setChannel($channel);

                $this->entityManager->persist($resetToken);
                $this->entityManager->flush();

                // Send notification via selected channel
                try {
                    $this->notificationService->sendPasswordResetNotification(
                        $user,
                        $token,
                        $channel
                    );

                    $this->addFlash('success', sprintf(
                        'Un code de réinitialisation a été envoyé via %s.',
                        $this->getChannelLabel($channel)
                    ));
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'envoi du code. Veuillez réessayer.');
                }
            } else {
                // Security: Don't reveal if email exists
                $this->addFlash('success', 
                    'Si un compte existe avec cet email, vous recevrez un code de réinitialisation.'
                );
            }

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(Request $request, string $token, UserPasswordHasherInterface $passwordHasher): Response
    {
        $resetToken = $this->entityManager->getRepository(PasswordResetToken::class)
            ->findOneBy(['token' => $token]);

        if (!$resetToken || $resetToken->isExpired()) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $resetToken->getUser();
            $newPassword = $form->get('plainPassword')->getData();

            // Hash and set new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            // Remove used token
            $this->entityManager->remove($resetToken);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function generateResetToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function getChannelLabel(string $channel): string
    {
        return match($channel) {
            'email' => 'email',
            'sms' => 'SMS',
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            default => 'notification'
        };
    }
}