<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TwoFactorAuthService;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/2fa')]
#[IsGranted('ROLE_USER')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private TwoFactorAuthService $twoFactorService
    ) {
    }

    #[Route('', name: 'app_2fa_settings')]
    public function settings(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $isTwoFactorEnabled = $this->twoFactorService->isTwoFactorEnabled($user);
        $backupCodesCount = $this->twoFactorService->getRemainingBackupCodesCount($user);

        return $this->render('two_factor/settings.html.twig', [
            'is_enabled' => $isTwoFactorEnabled,
            'backup_codes_count' => $backupCodesCount,
        ]);
    }

    #[Route('/enable', name: 'app_2fa_enable')]
    public function enable(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->twoFactorService->isTwoFactorEnabled($user)) {
            $this->addFlash('info', 'Two-factor authentication is already enabled.');
            return $this->redirectToRoute('app_2fa_settings');
        }

        $secret = $this->twoFactorService->enableTwoFactorAuth($user);

        return $this->render('two_factor/enable.html.twig', [
            'secret' => $secret,
        ]);
    }

    #[Route('/qr-code', name: 'app_2fa_qr_code')]
    public function qrCode(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getTotpSecret()) {
            throw $this->createNotFoundException('TOTP secret not found');
        }

        try {
            // Obtenir le contenu du QR code
            $qrCodeContent = $this->twoFactorService->getQrCodeContent($user);

            // Créer le QR code
            $qrCode = QrCode::create($qrCodeContent)
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
                ->setSize(300)
                ->setMargin(10)
                ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->setForegroundColor(new Color(0, 0, 0))
                ->setBackgroundColor(new Color(255, 255, 255));

            // Utiliser le writer PNG
            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            // Retourner la réponse avec l'image
            return new Response($result->getString(), 200, [
                'Content-Type' => $result->getMimeType(),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (\Exception $e) {
            // Log l'erreur
            error_log('QR Code generation error: ' . $e->getMessage());
            
            // Retourner une image d'erreur
            return $this->createErrorImage($e->getMessage());
        }
    }

    private function createErrorImage(string $message): Response
    {
        $image = imagecreate(300, 300);
        $bgColor = imagecolorallocate($image, 240, 240, 240);
        $textColor = imagecolorallocate($image, 200, 0, 0);
        
        imagestring($image, 3, 70, 130, 'QR Code Error', $textColor);
        imagestring($image, 2, 40, 150, 'Check logs for details', $textColor);
        
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return new Response($imageData, 200, [
            'Content-Type' => 'image/png'
        ]);
    }

    #[Route('/verify', name: 'app_2fa_verify', methods: ['POST'])]
    public function verify(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $code = $request->request->get('code');

        if (!$code) {
            $this->addFlash('error', 'Please enter a verification code.');
            return $this->redirectToRoute('app_2fa_enable');
        }

        if ($this->twoFactorService->verifyAndEnableTwoFactorAuth($user, $code)) {
            $this->addFlash('success', 'Two-factor authentication has been enabled successfully!');
            return $this->redirectToRoute('app_2fa_backup_codes', ['new' => true]);
        } else {
            $this->addFlash('error', 'Invalid verification code. Please try again.');
            return $this->redirectToRoute('app_2fa_enable');
        }
    }

    #[Route('/backup-codes', name: 'app_2fa_backup_codes')]
    public function backupCodes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->twoFactorService->isTwoFactorEnabled($user)) {
            return $this->redirectToRoute('app_2fa_settings');
        }

        $backupCodes = $user->getBackupCodes();

        return $this->render('two_factor/backup_codes.html.twig', [
            'backup_codes' => $backupCodes,
            'is_new' => $request->query->get('new', false),
        ]);
    }

    #[Route('/regenerate-backup-codes', name: 'app_2fa_regenerate_codes', methods: ['POST'])]
    public function regenerateBackupCodes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->twoFactorService->isTwoFactorEnabled($user)) {
            return $this->redirectToRoute('app_2fa_settings');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('regenerate-backup-codes', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_2fa_settings');
        }

        $this->twoFactorService->regenerateBackupCodes($user);
        $this->addFlash('success', 'Backup codes have been regenerated. Your old codes are no longer valid.');

        return $this->redirectToRoute('app_2fa_backup_codes', ['new' => true]);
    }

    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('disable-2fa', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_2fa_settings');
        }

        $this->twoFactorService->disableTwoFactorAuth($user);
        $this->addFlash('success', 'Two-factor authentication has been disabled.');

        return $this->redirectToRoute('app_2fa_settings');
    }

    #[Route('/debug', name: 'app_2fa_debug')]
    public function debug(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $debug = [
            'user_email' => $user->getEmail(),
            'has_secret' => $user->getTotpSecret() !== null,
            'secret_length' => $user->getTotpSecret() ? strlen($user->getTotpSecret()) : 0,
            'secret' => $user->getTotpSecret(),
            'is_enabled' => $user->isTotpAuthenticationEnabled(),
            'qr_url' => $this->generateUrl('app_2fa_qr_code', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        
        try {
            $debug['qr_content'] = $this->twoFactorService->getQrCodeContent($user);
        } catch (\Exception $e) {
            $debug['qr_error'] = $e->getMessage();
        }
        
        return new Response('<pre>' . json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>');
    }
}