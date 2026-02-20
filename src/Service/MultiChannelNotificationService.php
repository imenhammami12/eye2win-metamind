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
        $verifyUrl = "http://localhost:8000/verify-reset-code";
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — EyeTwin</title>
</head>
<body style="margin:0;padding:0;background-color:#07080f;font-family:'Poppins',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#07080f;padding:40px 16px;">
  <tr>
    <td align="center">
      <table width="620" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;width:100%;">

        <!-- TOP GRADIENT BAR -->
        <tr>
          <td style="height:4px;background-image:linear-gradient(to left,#ff0000 0%,#c6019a 51%,#ff0000 100%);background-size:200% auto;border-radius:4px 4px 0 0;"></td>
        </tr>

        <!-- HEADER -->
        <tr>
          <td align="center" style="background-color:#0b111f;border-left:1px solid rgba(255,255,255,0.07);border-right:1px solid rgba(255,255,255,0.07);padding:52px 48px 44px;">
            <p style="margin:0 0 36px;font-size:8px;font-weight:700;letter-spacing:6px;text-transform:uppercase;color:#3a3a55;font-family:Arial,sans-serif;">E-SPORT PLATFORM</p>

            <!-- Icon circle -->
            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 22px;">
              <tr>
                <td align="center" style="width:80px;height:80px;border-radius:50%;background:rgba(255,0,0,0.07);border:1px solid rgba(255,0,0,0.25);font-size:32px;line-height:80px;text-align:center;">🔐</td>
              </tr>
            </table>

            <h1 style="margin:0 0 10px;font-size:28px;font-weight:700;color:#ffffff;letter-spacing:3px;text-transform:uppercase;line-height:1;font-family:Arial,sans-serif;">Reset Password</h1>
            <p style="margin:0;font-size:11px;color:#6a6a88;text-transform:uppercase;letter-spacing:3px;font-weight:500;font-family:Arial,sans-serif;">Security · Account Recovery</p>
          </td>
        </tr>

        <!-- ACCENT STRIPE -->
        <tr>
          <td style="height:2px;background-image:linear-gradient(to left,#ff0000 0%,#c6019a 51%,#ff0000 100%);background-size:200% auto;border-left:1px solid rgba(255,255,255,0.07);border-right:1px solid rgba(255,255,255,0.07);"></td>
        </tr>

        <!-- BODY -->
        <tr>
          <td style="background-color:#0b111f;border-left:1px solid rgba(255,255,255,0.06);border-right:1px solid rgba(255,255,255,0.06);padding:44px 48px 40px;">

            <!-- Greeting -->
            <p style="margin:0 0 10px;font-size:18px;color:#d8dce8;font-weight:500;line-height:1.4;font-family:Arial,sans-serif;">
              Hello, <span style="color:#ffffff;font-weight:700;">{$username}</span> 👋
            </p>
            <p style="margin:0 0 32px;font-size:14px;color:#b0b4c4;line-height:1.9;font-weight:300;font-family:Arial,sans-serif;">
              We received a request to reset the password for your
              <span style="color:#d8dce8;font-weight:500;">EyeTwin</span> account.
              Click the button below to set a new password. This link is valid for
              <span style="color:#ffffff;font-weight:600;">1 hour</span>.
            </p>

            <!-- Divider -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:32px;">
              <tr><td style="height:1px;background:linear-gradient(to right,rgba(255,0,0,0.35),rgba(198,1,154,0.35),rgba(255,0,0,0.35));"></td></tr>
            </table>

            <!-- CTA BUTTON -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:32px;">
              <tr>
                <td align="center">
                  <a href="{$resetUrl}"
                     style="display:inline-block;padding:18px 52px;background-image:linear-gradient(to left,#ff0000 0%,#c6019a 51%,#ff0000 100%);background-size:200% auto;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-family:Arial,sans-serif;">
                    🔑 &nbsp; Reset My Password
                  </a>
                </td>
              </tr>
            </table>

            <!-- Expiry info strip -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:32px;">
              <tr>
                <td style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:14px 18px;">
                  <table cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td width="28" valign="top" style="font-size:16px;padding-top:1px;">⏱️</td>
                      <td style="padding-left:10px;font-size:13px;color:#b0b4c4;line-height:1.7;font-weight:300;font-family:Arial,sans-serif;">
                        This link expires in <span style="color:#ffffff;font-weight:600;">60 minutes</span>.
                        If you didn't request this, you can safely ignore this email — your account will not be affected.
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- Divider -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
              <tr><td style="height:1px;background:rgba(255,255,255,0.06);"></td></tr>
            </table>

            <!-- Fallback token section -->
            <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;font-family:Arial,sans-serif;">Button not working?</p>
            <p style="margin:0 0 14px;font-size:13px;color:#b0b4c4;line-height:1.8;font-weight:300;font-family:Arial,sans-serif;">
              Copy and paste the full reset code below into the verification page:
            </p>

            <!-- Token box (code-input style) -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
              <tr>
                <td style="background:rgba(255,255,255,0.06);border:2px solid rgba(255,255,255,0.12);border-radius:10px;padding:14px 18px;">
                  <p style="margin:0;font-size:12px;color:#c8ccdc;font-family:'Courier New',monospace;word-break:break-all;letter-spacing:1px;line-height:1.6;">{$token}</p>
                </td>
              </tr>
            </table>



            <!-- Security warning box -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:36px;">
              <tr>
                <td style="background:#0d1020;border:1.5px solid rgba(255,0,0,0.2);border-left:3px solid #ff0000;border-radius:0 8px 8px 0;padding:16px 20px;">
                  <table cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td width="30" valign="top" style="font-size:16px;padding-top:1px;">🛡️</td>
                      <td style="padding-left:10px;">
                        <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#ff5555;font-family:Arial,sans-serif;">Security Notice</p>
                        <p style="margin:0;font-size:13px;color:#b0b4c4;line-height:1.7;font-weight:300;font-family:Arial,sans-serif;">Never share this link or code with anyone. EyeTwin staff will never ask you for your reset link.</p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- Closing -->
            <p style="margin:0 0 14px;font-size:14px;color:#b0b4c4;line-height:1.9;font-weight:300;font-family:Arial,sans-serif;">
              If you have any issues, please contact our support team.
            </p>
            <p style="margin:0;font-size:14px;color:#b0b4c4;line-height:1.7;font-weight:300;font-family:Arial,sans-serif;">
              Stay secure,<br>
              <span style="color:#ffffff;font-weight:700;font-family:Arial,sans-serif;">The EyeTwin Team</span>
            </p>

          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td align="center" style="background-color:#060810;border:1px solid rgba(255,255,255,0.045);border-top:none;border-radius:0 0 4px 4px;padding:24px 48px 28px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
              <tr><td style="height:1px;background:linear-gradient(to right,rgba(255,0,0,0.25),rgba(198,1,154,0.25),rgba(255,0,0,0.25));"></td></tr>
            </table>
            <p style="margin:0 0 4px;font-size:11px;color:#6a6a7a;line-height:1.8;font-weight:300;font-family:Arial,sans-serif;">This email was sent automatically — please do not reply.</p>
            <p style="margin:0;font-size:11px;color:#6a6a7a;line-height:1.8;font-weight:300;font-family:Arial,sans-serif;">
              &copy; {$year} <span style="color:#ff0000;font-weight:500;">EyeTwin E-Sport Platform</span> — All rights reserved
            </p>
          </td>
        </tr>

        <!-- BOTTOM GRADIENT BAR -->
        <tr>
          <td style="height:3px;background-image:linear-gradient(to left,#ff0000 0%,#c6019a 51%,#ff0000 100%);background-size:200% auto;border-radius:0 0 4px 4px;"></td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;
    }
}