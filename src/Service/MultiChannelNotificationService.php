<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class MultiChannelNotificationService
{
    private MailerInterface $mailer;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $fromEmail;
    private string $fromName;
    
    // Twilio credentials (for SMS)
    private ?string $twilioAccountSid;
    private ?string $twilioAuthToken;
    private ?string $twilioPhoneNumber;
    
    // Telegram credentials (FREE for dev)
    private ?string $telegramBotToken;
    
    // For development: log messages instead of sending
    private bool $devMode;

    public function __construct(
        MailerInterface $mailer,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $fromEmail,
        string $fromName,
        ?string $twilioAccountSid = null,
        ?string $twilioAuthToken = null,
        ?string $twilioPhoneNumber = null,
        ?string $telegramBotToken = null,
        bool $devMode = false
    ) {
        $this->mailer = $mailer;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->twilioAccountSid = $twilioAccountSid;
        $this->twilioAuthToken = $twilioAuthToken;
        $this->twilioPhoneNumber = $twilioPhoneNumber;
        $this->telegramBotToken = $telegramBotToken;
        $this->devMode = $devMode;
    }

    public function sendPasswordResetNotification(User $user, string $token, string $channel): void
    {
        $message = $this->generateMessage($token);
        
        switch ($channel) {
            case 'email':
                $this->sendEmail($user, $token);
                break;
            case 'sms':
                $this->sendSMS($user, $message);
                break;
            case 'telegram':
                $this->sendTelegram($user, $message);
                break;
            case 'whatsapp':
                $this->sendWhatsApp($user, $message);
                break;
            default:
                throw new \InvalidArgumentException("Unknown channel: $channel");
        }
    }

    private function sendEmail(User $user, string $token): void
    {
        $resetUrl = "http://localhost:8000/reset-password/{$token}"; // Adjust for production
        
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - EyeTwin')
            ->html($this->generateEmailTemplate($user->getUsername(), $resetUrl, $token));

        if ($this->devMode) {
            $this->logger->info('DEV MODE - Email would be sent', [
                'to' => $user->getEmail(),
                'token' => $token,
                'url' => $resetUrl
            ]);
        } else {
            $this->mailer->send($email);
        }
    }

    private function sendSMS(User $user, string $message): void
    {
        if (!$user->getPhone()) {
            throw new \RuntimeException('User does not have a phone number');
        }

        if ($this->devMode) {
            $this->logger->info('DEV MODE - SMS would be sent', [
                'to' => $user->getPhone(),
                'message' => $message
            ]);
            return;
        }

        if (!$this->twilioAccountSid || !$this->twilioAuthToken) {
            throw new \RuntimeException('Twilio credentials not configured');
        }

        try {
            // Formater le numéro au format E.164 (ex: +21612345678)
            $phoneNumber = $user->getPhone();
            
            // Si le numéro ne commence pas par +, on assume qu'il faut ajouter l'indicatif
            if (!str_starts_with($phoneNumber, '+')) {
                // Pour la Tunisie, ajouter +216
                $phoneNumber = '+216' . ltrim($phoneNumber, '0');
            }
            
            $this->logger->info('Attempting to send SMS', [
                'to' => $phoneNumber,
                'from' => $this->twilioPhoneNumber
            ]);

            // Twilio API call
            $response = $this->httpClient->request('POST', 
                "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioAccountSid}/Messages.json",
                [
                    'auth_basic' => [$this->twilioAccountSid, $this->twilioAuthToken],
                    'body' => [
                        'From' => $this->twilioPhoneNumber,
                        'To' => $phoneNumber,
                        'Body' => $message
                    ]
                ]
            );
            
            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);
            
            // Vérifier le statut de l'envoi
            if (isset($content['status'])) {
                $this->logger->info('SMS sent with status: ' . $content['status'], [
                    'sid' => $content['sid'] ?? 'N/A',
                    'status' => $content['status'],
                    'error_code' => $content['error_code'] ?? null,
                    'error_message' => $content['error_message'] ?? null
                ]);
                
                // Si le compte est en trial, vérifier si le numéro est vérifié
                if (isset($content['error_code']) && $content['error_code'] == 21608) {
                    throw new \RuntimeException('Numéro non vérifié. En mode Trial, vous devez vérifier votre numéro sur console.twilio.com');
                }
            }
            
            error_log("SMS Response: " . json_encode($content, JSON_PRETTY_PRINT));
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send SMS', [
                'error' => $e->getMessage(),
                'phone' => $phoneNumber ?? $user->getPhone()
            ]);
            throw new \RuntimeException('Erreur lors de l\'envoi du SMS: ' . $e->getMessage());
        }
    }

    private function sendTelegram(User $user, string $message): void
    {
        if (!$user->getTelegramChatId()) {
            throw new \RuntimeException('User does not have a Telegram chat ID');
        }

        if ($this->devMode) {
            $this->logger->info('DEV MODE - Telegram message would be sent', [
                'chat_id' => $user->getTelegramChatId(),
                'message' => $message
            ]);
            return;
        }

        if (!$this->telegramBotToken) {
            throw new \RuntimeException('Telegram bot token not configured');
        }

        try {
            $response = $this->httpClient->request('POST', 
                "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage",
                [
                    'json' => [
                        'chat_id' => $user->getTelegramChatId(),
                        'text' => $message,
                        'parse_mode' => 'HTML'
                    ]
                ]
            );

            $this->logger->info('Telegram message sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Telegram message', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function sendWhatsApp(User $user, string $message): void
    {
        if (!$user->getPhone()) {
            throw new \RuntimeException('User does not have a phone number');
        }

        if ($this->devMode) {
            $this->logger->info('DEV MODE - WhatsApp message would be sent', [
                'to' => $user->getPhone(),
                'message' => $message
            ]);
            return;
        }

        if (!$this->twilioAccountSid || !$this->twilioAuthToken) {
            throw new \RuntimeException('Twilio credentials not configured for WhatsApp');
        }

        try {
            // Format phone for WhatsApp (must include whatsapp: prefix)
            $phoneNumber = $user->getPhone();
            if (!str_starts_with($phoneNumber, '+')) {
                $phoneNumber = '+216' . ltrim($phoneNumber, '0');
            }
            
            $whatsappNumber = 'whatsapp:' . $phoneNumber;
            $fromWhatsapp = 'whatsapp:' . $this->twilioPhoneNumber;

            $response = $this->httpClient->request('POST', 
                "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioAccountSid}/Messages.json",
                [
                    'auth_basic' => [$this->twilioAccountSid, $this->twilioAuthToken],
                    'body' => [
                        'From' => $fromWhatsapp,
                        'To' => $whatsappNumber,
                        'Body' => $message
                    ]
                ]
            );

            $this->logger->info('WhatsApp message sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send WhatsApp message', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function generateMessage(string $token): string
    {
        $resetUrl = "http://localhost:8000/reset-password/{$token}";
        
        return "🔐 EyeTwin - Réinitialisation de mot de passe\n\n" .
               "Votre code de réinitialisation: {$token}\n\n" .
               "Ou cliquez sur ce lien:\n{$resetUrl}\n\n" .
               "⏱ Ce code expire dans 1 heure.\n" .
               "Si vous n'avez pas demandé cette réinitialisation, ignorez ce message.";
    }

    private function generateEmailTemplate(string $username, string $resetUrl, string $token): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: 'Inter', Arial, sans-serif;
                    background: linear-gradient(135deg, #1a0e2e 0%, #2d1b3d 100%);
                    margin: 0;
                    padding: 40px 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: rgba(255, 255, 255, 0.95);
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                }
                .header {
                    background: linear-gradient(135deg, #ff3c64 0%, #5a67d8 100%);
                    padding: 40px;
                    text-align: center;
                    color: white;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                }
                .content {
                    padding: 40px;
                    color: #333;
                }
                .token-box {
                    background: #f8f9fa;
                    border: 2px dashed #5a67d8;
                    border-radius: 12px;
                    padding: 20px;
                    text-align: center;
                    margin: 30px 0;
                }
                .token {
                    font-size: 24px;
                    font-weight: bold;
                    color: #ff3c64;
                    letter-spacing: 2px;
                    word-break: break-all;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #ff3c64 0%, #ff1744 100%);
                    color: white !important;
                    padding: 16px 40px;
                    border-radius: 12px;
                    text-decoration: none;
                    font-weight: bold;
                    margin: 20px 0;
                    text-transform: uppercase;
                }
                .footer {
                    background: #f8f9fa;
                    padding: 30px;
                    text-align: center;
                    color: #666;
                    font-size: 13px;
                }
                .warning {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 4px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Réinitialisation de mot de passe</h1>
                </div>
                <div class="content">
                    <p>Bonjour <strong>{$username}</strong>,</p>
                    
                    <p>Vous avez demandé la réinitialisation de votre mot de passe EyeTwin E-Sport Platform.</p>
                    
                    <div class="token-box">
                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Votre code de réinitialisation:</p>
                        <div class="token">{$token}</div>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="{$resetUrl}" class="button">Réinitialiser mon mot de passe</a>
                    </p>
                    
                    <div class="warning">
                        <strong>⏱ Important:</strong> Ce lien expire dans <strong>1 heure</strong>.
                    </div>
                    
                    <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.</p>
                </div>
                <div class="footer">
                    <p><strong>EyeTwin E-Sport Platform</strong></p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}