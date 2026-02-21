<?php

namespace App\Controller;

use App\Entity\CoinPurchase;
use App\Entity\User;
use App\Repository\CoinPurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/coins')]
class CoinController extends AbstractController
{
    // Coin packages: coins => price in EUR cents
    private const COIN_PACKAGES = [
        100  => ['coins' => 100,  'price' => 199,  'label' => 'Starter Pack',   'popular' => false],
        300  => ['coins' => 300,  'price' => 499,  'label' => 'Popular Pack',   'popular' => true],
        700  => ['coins' => 700,  'price' => 999,  'label' => 'Pro Pack',        'popular' => false],
        1500 => ['coins' => 1500, 'price' => 1799, 'label' => 'Elite Pack',      'popular' => false],
    ];

    public function __construct(
        private string $stripeSecretKey,
        private string $stripeWebhookSecret,
    ) {}

    #[Route('/', name: 'coins_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('coins/index.html.twig', [
            'packages' => self::COIN_PACKAGES,
            'user' => $user,
        ]);
    }

    #[Route('/checkout/{coins}', name: 'coins_checkout', methods: ['POST'])]
    public function checkout(int $coins, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('coins_buy_' . $coins, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!isset(self::COIN_PACKAGES[$coins])) {
            throw $this->createNotFoundException('Invalid coin package.');
        }

        $package = self::COIN_PACKAGES[$coins];

        /** @var User $user */
        $user = $this->getUser();

        Stripe::setApiKey($this->stripeSecretKey);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'EyeTwin Coins — ' . $package['label'],
                        'description' => $package['coins'] . ' EyeTwin Coins for live stream access',
                    ],
                    'unit_amount' => $package['price'],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $this->generateUrl('coins_success', ['coins' => $coins], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->generateUrl('coins_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'metadata' => [
                'user_id' => $user->getId(),
                'coins_amount' => $coins,
            ],
        ]);

        // Save pending purchase
        // We'll confirm in webhook, but also in success route as fallback

        return $this->redirect($session->url);
    }

    #[Route('/success/{coins}', name: 'coins_success')]
    public function success(int $coins, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $sessionId = $request->query->get('session_id');

        if (!$sessionId || !isset(self::COIN_PACKAGES[$coins])) {
            return $this->redirectToRoute('coins_index');
        }

        /** @var User $user */
        $user = $this->getUser();

        // Check if already processed
        $existing = $em->getRepository(CoinPurchase::class)->findOneBy(['stripeSessionId' => $sessionId]);
        if ($existing && $existing->getStatus() === 'completed') {
            $this->addFlash('info', 'This purchase has already been processed.');
            return $this->redirectToRoute('user_profile');
        }

        Stripe::setApiKey($this->stripeSecretKey);

        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                if (!$existing) {
                    $package = self::COIN_PACKAGES[$coins];

                    $purchase = new CoinPurchase();
                    $purchase->setUser($user);
                    $purchase->setCoinsAmount($package['coins']);
                    $purchase->setPricePaid(number_format($package['price'] / 100, 2));
                    $purchase->setStripeSessionId($sessionId);
                    $purchase->setStatus('completed');
                    $purchase->setCompletedAt(new \DateTime());
                    $em->persist($purchase);

                    $user->setCoinBalance($user->getCoinBalance() + $package['coins']);
                    $em->flush();

                    $this->addFlash('success', '🎉 ' . $package['coins'] . ' EyeTwin Coins have been added to your account!');
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Could not verify payment. Please contact support if coins were not credited.');
        }

        return $this->redirectToRoute('user_profile');
    }

    #[Route('/webhook', name: 'coins_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        Stripe::setApiKey($this->stripeSecretKey);

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (\Exception $e) {
            return new Response('Webhook error: ' . $e->getMessage(), 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $userId = $session->metadata->user_id ?? null;
            $coinsAmount = (int)($session->metadata->coins_amount ?? 0);

            if ($userId && $coinsAmount && isset(self::COIN_PACKAGES[$coinsAmount])) {
                $existing = $em->getRepository(CoinPurchase::class)
                    ->findOneBy(['stripeSessionId' => $session->id]);

                if (!$existing) {
                    $user = $em->getRepository(User::class)->find($userId);
                    if ($user) {
                        $package = self::COIN_PACKAGES[$coinsAmount];

                        $purchase = new CoinPurchase();
                        $purchase->setUser($user);
                        $purchase->setCoinsAmount($package['coins']);
                        $purchase->setPricePaid(number_format($package['price'] / 100, 2));
                        $purchase->setStripeSessionId($session->id);
                        $purchase->setStatus('completed');
                        $purchase->setCompletedAt(new \DateTime());
                        $em->persist($purchase);

                        $user->setCoinBalance($user->getCoinBalance() + $package['coins']);
                        $em->flush();
                    }
                }
            }
        }

        return new Response('OK', 200);
    }
}
