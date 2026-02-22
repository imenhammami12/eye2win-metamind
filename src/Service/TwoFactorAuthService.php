<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class TwoFactorAuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TotpAuthenticatorInterface $totpAuthenticator
    ) {
    }

    /**
     * Enable 2FA for a user and generate a secret
     */
    public function enableTwoFactorAuth(User $user): string
    {
        // Generate TOTP secret
        $secret = $this->totpAuthenticator->generateSecret();
        
        // Set the secret but don't enable yet (user needs to verify first)
        $user->setTotpSecret($secret);
        
        $this->entityManager->flush();
        
        return $secret;
    }

    /**
     * Verify the code and fully enable 2FA
     */
    public function verifyAndEnableTwoFactorAuth(User $user, string $code): bool
    {
        if (!$user->getTotpSecret()) {
            return false;
        }

        // Verify the code
        if ($this->totpAuthenticator->checkCode($user, $code)) {
            // Generate backup codes
            $backupCodes = $this->generateBackupCodes();
            
            $user->setIsTotpEnabled(true);
            $user->setBackupCodes($backupCodes);
            
            $this->entityManager->flush();
            
            return true;
        }

        return false;
    }

    /**
     * Disable 2FA for a user
     */
    public function disableTwoFactorAuth(User $user): void
    {
        $user->setIsTotpEnabled(false);
        $user->setTotpSecret(null);
        $user->setBackupCodes(null);
        
        $this->entityManager->flush();
    }

    /**
     * Generate QR code content for the authenticator app
     */
        public function getQrCodeContent(User $user): string
{
    if (!$user->getTotpSecret()) {
        throw new \RuntimeException('TOTP secret not set for user');
    }

    $email = $user->getEmail();
    if (!$email) {
        throw new \RuntimeException('User email not set');
    }

    $issuer = 'EyeTwin';
    
    // Format standard pour TOTP
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        urlencode($issuer),
        urlencode($email),
        $user->getTotpSecret(),
        urlencode($issuer)
    );
}

    /**
     * Generate backup codes
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateBackupCode();
        }
        
        return $codes;
    }

    /**
     * Regenerate backup codes for a user
     */
    public function regenerateBackupCodes(User $user): array
    {
        $backupCodes = $this->generateBackupCodes();
        $user->setBackupCodes($backupCodes);
        
        $this->entityManager->flush();
        
        return $backupCodes;
    }

    /**
     * Generate a single backup code
     */
    private function generateBackupCode(): string
    {
        // Generate a 10-character alphanumeric code
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < 10; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        // Format as XXXXX-XXXXX for readability
        return substr($code, 0, 5) . '-' . substr($code, 5, 5);
    }

    /**
     * Verify a backup code
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->getBackupCodes();
        
        if (!$backupCodes) {
            return false;
        }

        // Check if code exists in backup codes
        if (in_array($code, $backupCodes, true)) {
            // Invalidate the used backup code
            $user->invalidateBackupCode($code);
            $this->entityManager->flush();
            
            return true;
        }

        return false;
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isTwoFactorEnabled(User $user): bool
    {
        return $user->isTotpAuthenticationEnabled();
    }

    /**
     * Get remaining backup codes count
     */
    public function getRemainingBackupCodesCount(User $user): int
    {
        $backupCodes = $user->getBackupCodes();
        return $backupCodes ? count($backupCodes) : 0;
    }
}