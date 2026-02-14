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

    #[Route('/face-pre-login', name: 'face_pre_login_page')]
    public function facePreLoginPage(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/security/face_pre_login.html.twig');
    }

    #[Route('/face-pre-login-verify', name: 'face_pre_login_verify', methods: ['POST'])]
    public function facePreLoginVerify(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // Récupérer les données depuis FormData
        $descriptorJson = $request->request->get('descriptor');
        
        if (!$descriptorJson) {
            // Fallback: essayer de lire depuis JSON body
            $data = json_decode($request->getContent(), true);
            $descriptor = $data['descriptor'] ?? null;
        } else {
            $descriptor = json_decode($descriptorJson, true);
        }

        if (!$descriptor || !is_array($descriptor)) {
            $this->logger->error('Invalid descriptor received', [
                'formData' => $request->request->all(),
                'jsonBody' => $request->getContent()
            ]);
            
            return $this->json([
                'success' => false, 
                'message' => 'Données manquantes ou invalides'
            ], 400);
        }

        // Chercher parmi TOUS les utilisateurs avec face enregistrée
        $users = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            return $this->json([
                'success' => false, 
                'message' => 'Aucun visage enregistré dans le système'
            ], 404);
        }

        $matchedUser = null;
        $minDistance = 0.6;

        foreach ($users as $user) {
            $stored = json_decode($user->getFaceDescriptor(), true);
            if (!$stored || !is_array($stored)) {
                continue;
            }

            $distance = $this->euclideanDistance($descriptor, $stored);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $matchedUser = $user;
            }
        }

        if (!$matchedUser) {
            return $this->json([
                'success' => false,
                'message' => 'Visage non reconnu. Veuillez réessayer.',
            ], 401);
        }

        // ✅ CORRIGÉ: Vérifier si l'utilisateur a ROLE_ADMIN ou ROLE_SUPER_ADMIN
        $roles = $matchedUser->getRoles();
        $isAdmin = in_array('ROLE_ADMIN', $roles) || in_array('ROLE_SUPER_ADMIN', $roles);
        
        if (!$isAdmin) {
            $this->logger->warning('Face recognized but user is not admin', [
                'user_id' => $matchedUser->getId(),
                'email' => $matchedUser->getEmail(),
                'roles' => $roles
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Accès refusé — compte non administrateur.',
            ], 403);
        }

        $session = $this->requestStack->getSession();
        $session->set('face_pre_login_verified', true);
        $session->set('face_pre_login_verified_user_id', (int)$matchedUser->getId());
        $session->set('face_pre_login_verified_email', $matchedUser->getEmail());

        $this->logger->info('Face pre-login verified', [
            'user_id' => $matchedUser->getId(),
            'email'   => $matchedUser->getEmail(),
            'distance' => $minDistance,
            'roles' => $roles
        ]);

        return $this->json([
            'success'  => true,
            'message'  => 'Visage reconnu — entrez votre mot de passe',
            'redirect' => $this->generateUrl('admin_login'),
        ]);
    }

    #[Route('/face-pre-check', name: 'face_pre_check', methods: ['POST'])]
    public function facePreCheck(
        Request $request, 
        EntityManagerInterface $em
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json(['requiresFace' => false]);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$user->getFaceDescriptor()) {
            return $this->json(['requiresFace' => false]);
        }

        $session = $this->requestStack->getSession();

        if (
            $session->get('face_pre_login_verified') === true &&
            (int)$session->get('face_pre_login_verified_user_id') === (int)$user->getId()
        ) {
            return $this->json(['requiresFace' => false]);
        }

        $session->set('face_pre_login_email', $email);
        $session->set('face_pre_login_verified_email', $email);
        $session->set('face_pre_login_user_id', (int)$user->getId());

        return $this->json([
            'requiresFace' => true,
            'redirect'     => $this->generateUrl('face_pre_login_page'),
        ]);
    }

    #[Route('/face-login', name: 'face_login')]
    public function faceLogin(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }
        return $this->render('security/face_login.html.twig');
    }

    #[Route('/face-login/verify', name: 'face_login_verify', methods: ['POST'])]
    public function verifyFace(
        Request $request,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        AuthenticatorInterface $authenticator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['descriptor'], $data['image'])) {
            return $this->json([
                'success' => false, 
                'message' => 'Données invalides'
            ], 400);
        }

        $users = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->andWhere('u.accountStatus = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();

        $matchedUser = null;
        $minDist = 0.6;

        foreach ($users as $u) {
            $s = json_decode($u->getFaceDescriptor(), true);
            if ($s) {
                $d = $this->euclideanDistance($data['descriptor'], $s);
                if ($d < $minDist) {
                    $minDist = $d;
                    $matchedUser = $u;
                }
            }
        }

        if ($matchedUser) {
            $matchedUser->setLastLogin(new \DateTime());
            $em->flush();
            $userAuthenticator->authenticateUser($matchedUser, $authenticator, $request);
            
            return $this->json([
                'success' => true, 
                'redirect' => $this->generateUrl('admin_dashboard')
            ]);
        }

        return $this->json([
            'success' => false, 
            'message' => 'Visage non reconnu'
        ], 401);
    }

    #[Route('/face/register', name: 'face_register', methods: ['POST'])]
    public function registerFace(
        Request $request, 
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->json([
                'success' => false, 
                'message' => 'Non connecté'
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['descriptor'], $data['image'])) {
            return $this->json([
                'success' => false, 
                'message' => 'Données invalides'
            ], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $dir  = $this->params->get('faces_directory');
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = $user->getId() . '_' . time() . '.jpg';
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['image']));
        file_put_contents($dir . '/' . $filename, $img);

        if ($user->getFaceImage()) {
            $old = $dir . '/' . basename($user->getFaceImage());
            if (file_exists($old)) {
                unlink($old);
            }
        }

        $user->setFaceDescriptor(json_encode($data['descriptor']));
        $user->setFaceImage('uploads/faces/' . $filename);
        $em->flush();

        return $this->json([
            'success' => true, 
            'message' => 'Visage enregistré avec succès'
        ]);
    }

    #[Route('/face/remove', name: 'face_remove', methods: ['POST'])]
    public function removeFace(EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->json([
                'success' => false, 
                'message' => 'Non connecté'
            ], 401);
        }

        /** @var User $user */
        $user = $this->getUser();
        
        if ($user->getFaceImage()) {
            $path = $this->params->get('faces_directory') . '/' . basename($user->getFaceImage());
            if (file_exists($path)) {
                unlink($path);
            }
        }
        
        $user->setFaceDescriptor(null);
        $user->setFaceImage(null);
        $em->flush();

        return $this->json([
            'success' => true, 
            'message' => 'Visage supprimé avec succès'
        ]);
    }

    private function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return PHP_FLOAT_MAX;
        }
        
        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $sum += ($a[$i] - $b[$i]) ** 2;
        }
        
        return sqrt($sum);
    }
}