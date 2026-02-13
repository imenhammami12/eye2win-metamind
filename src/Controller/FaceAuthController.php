<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class FaceAuthController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $params,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {}

    #[Route('/face-login', name: 'face_login')]
    public function faceLogin(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }
        
        return $this->render('security/face_login.html.twig');
    }

    /**
     * PAGE DE VÉRIFICATION FACIALE OBLIGATOIRE
     */
    #[Route('/face-verify-required', name: 'face_verify_required')]
    public function faceVerifyRequired(): Response
    {
        $session = $this->requestStack->getSession();
        $pendingUserId = $session->get('pending_face_verification');
        $username = $session->get('pending_face_username');
        
        if (!$pendingUserId) {
            $this->addFlash('error', 'Session expirée. Veuillez vous reconnecter.');
            return $this->redirectToRoute('admin_login');
        }
        
        return $this->render('admin/security/face_verify_required.html.twig', [
            'username' => $username
        ]);
    }

    /**
     * VÉRIFICATION FACIALE POST-LOGIN
     */
    #[Route('/face-verify-check', name: 'face_verify_check', methods: ['POST'])]
    public function faceVerifyCheck(
        Request $request,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        AuthenticatorInterface $authenticator
    ): JsonResponse {
        try {
            $session = $this->requestStack->getSession();
            $pendingUserId = $session->get('pending_face_verification');
            
            $this->logger->info('Face verification attempt', [
                'pending_user_id' => $pendingUserId,
                'has_session' => $session->isStarted()
            ]);
            
            if (!$pendingUserId) {
                return $this->json([
                    'success' => false,
                    'message' => 'Session expirée. Veuillez vous reconnecter.'
                ], 401);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['descriptor'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données biométriques manquantes'
                ], 400);
            }

            $user = $em->getRepository(User::class)->find($pendingUserId);
            
            if (!$user) {
                $this->logger->error('User not found during face verification', [
                    'user_id' => $pendingUserId
                ]);
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ], 404);
            }

            $userDescriptor = $data['descriptor'];
            $storedDescriptor = json_decode($user->getFaceDescriptor(), true);
            
            if (!$storedDescriptor || !is_array($storedDescriptor)) {
                $this->logger->error('No face descriptor stored for user', [
                    'user_id' => $user->getId()
                ]);
                return $this->json([
                    'success' => false,
                    'message' => 'Aucun visage enregistré pour cet utilisateur'
                ], 404);
            }

            $distance = $this->calculateEuclideanDistance($userDescriptor, $storedDescriptor);
            
            $this->logger->info('Face comparison result', [
                'user_id' => $user->getId(),
                'distance' => $distance,
                'threshold' => 0.6
            ]);
            
            if ($distance < 0.6) {
                // Visage vérifié ! Connecter l'utilisateur
                $session->remove('pending_face_verification');
                $session->remove('pending_face_username');
                
                $user->setLastLogin(new \DateTime());
                $em->flush();
                
                $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
                
                $this->logger->info('Face verification successful', [
                    'user_id' => $user->getId()
                ]);
                
                return $this->json([
                    'success' => true,
                    'message' => 'Vérification réussie',
                    'redirect' => $this->generateUrl('admin_dashboard')
                ]);
            }

            $this->logger->warning('Face verification failed - distance too high', [
                'user_id' => $user->getId(),
                'distance' => $distance
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Visage non reconnu. Distance: ' . round($distance, 2) . ' (seuil: 0.6)'
            ], 401);
            
        } catch (\Exception $e) {
            $this->logger->error('Face verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/face-login/verify', name: 'face_login_verify', methods: ['POST'])]
    public function verifyFace(
        Request $request,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        AuthenticatorInterface $authenticator
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['descriptor']) || !isset($data['image'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données invalides'
                ], 400);
            }

            $userDescriptor = $data['descriptor'];
            
            $users = $em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('u.faceDescriptor IS NOT NULL')
                ->andWhere('u.accountStatus = :status')
                ->setParameter('status', 'active')
                ->getQuery()
                ->getResult();

            if (empty($users)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Aucun utilisateur enregistré avec reconnaissance faciale'
                ], 404);
            }

            $matchedUser = null;
            $minDistance = 0.6;

            foreach ($users as $user) {
                $storedDescriptor = json_decode($user->getFaceDescriptor(), true);
                
                if ($storedDescriptor && is_array($storedDescriptor)) {
                    $distance = $this->calculateEuclideanDistance($userDescriptor, $storedDescriptor);
                    
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $matchedUser = $user;
                    }
                }
            }

            if ($matchedUser) {
                $matchedUser->setLastLogin(new \DateTime());
                $em->flush();
                
                $userAuthenticator->authenticateUser(
                    $matchedUser,
                    $authenticator,
                    $request
                );
                
                $this->logger->info('Face login successful', [
                    'user_id' => $matchedUser->getId()
                ]);
                
                return $this->json([
                    'success' => true,
                    'message' => 'Authentification réussie',
                    'redirect' => $this->generateUrl('admin_dashboard'),
                    'user' => $matchedUser->getUsername()
                ]);
            }

            return $this->json([
                'success' => false,
                'message' => 'Visage non reconnu. Veuillez réessayer.'
            ], 401);
            
        } catch (\Exception $e) {
            $this->logger->error('Face login error', [
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/face/register', name: 'face_register', methods: ['POST'])]
    public function registerFace(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->json([
                'success' => false, 
                'message' => 'Vous devez être connecté'
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['descriptor']) || !isset($data['image'])) {
            return $this->json([
                'success' => false, 
                'message' => 'Données invalides'
            ], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        try {
            $facesDirectory = $this->params->get('faces_directory');
            
            if (!is_dir($facesDirectory)) {
                mkdir($facesDirectory, 0777, true);
            }
            
            $filename = $user->getId() . '_' . time() . '.jpg';
            $filePath = $facesDirectory . '/' . $filename;
            
            $imageData = $data['image'];
            $imageContent = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
            
            if ($imageContent === false) {
                throw new \Exception('Impossible de décoder l\'image');
            }
            
            file_put_contents($filePath, $imageContent);
            
            if ($user->getFaceImage()) {
                $oldImagePath = $facesDirectory . '/' . basename($user->getFaceImage());
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            $user->setFaceDescriptor(json_encode($data['descriptor']));
            $user->setFaceImage('uploads/faces/' . $filename);
            
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Reconnaissance faciale enregistrée avec succès'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Face registration error', [
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/face/remove', name: 'face_remove', methods: ['POST'])]
    public function removeFace(
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->json([
                'success' => false, 
                'message' => 'Vous devez être connecté'
            ], 401);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        try {
            if ($user->getFaceImage()) {
                $facesDirectory = $this->params->get('faces_directory');
                $imagePath = $facesDirectory . '/' . basename($user->getFaceImage());
                
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $user->setFaceDescriptor(null);
            $user->setFaceImage(null);
            
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Reconnaissance faciale désactivée'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateEuclideanDistance(array $descriptor1, array $descriptor2): float
    {
        if (count($descriptor1) !== count($descriptor2)) {
            return PHP_FLOAT_MAX;
        }
        
        $sum = 0.0;
        for ($i = 0; $i < count($descriptor1); $i++) {
            $diff = $descriptor1[$i] - $descriptor2[$i];
            $sum += $diff * $diff;
        }
        
        return sqrt($sum);
    }
}