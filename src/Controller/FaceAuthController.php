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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class FaceAuthController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $params,
        private LoggerInterface $logger
    ) {}

    // ─────────────────────────────────────────────────────────────
    // PAGE : Vérification faciale avant login
    // Accessible uniquement si NON connecté
    // ─────────────────────────────────────────────────────────────
    #[Route('/admin/face-verify', name: 'admin_face_verify')]
    public function faceVerifyPage(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/security/face_verify.html.twig');
    }

    // ─────────────────────────────────────────────────────────────
    // API : Vérifie le visage et connecte l'utilisateur
    // ─────────────────────────────────────────────────────────────
    #[Route('/admin/face-verify/submit', name: 'admin_face_verify_submit', methods: ['POST'])]
    public function faceVerifySubmit(
        Request $request,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        AuthenticatorInterface $authenticator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['descriptor'])) {
            return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Chercher tous les admins avec une face enregistrée
        $users = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.faceDescriptor IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            return $this->json(['success' => false, 'message' => 'Aucun visage enregistré'], 404);
        }

        $matchedUser = null;
        $minDistance = 0.5; // seuil strict pour le login

        foreach ($users as $user) {
            $stored = json_decode($user->getFaceDescriptor(), true);
            if (!$stored || !is_array($stored)) continue;

            $distance = $this->euclideanDistance($data['descriptor'], $stored);
            $this->logger->debug('Face distance', ['email' => $user->getEmail(), 'distance' => $distance]);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $matchedUser = $user;
            }
        }

        if (!$matchedUser) {
            return $this->json(['success' => false, 'message' => 'Visage non reconnu. Réessayez.'], 401);
        }

        // Vérifier que c'est bien un admin
        $roles = $matchedUser->getRoles();
        if (!in_array('ROLE_ADMIN', $roles) && !in_array('ROLE_SUPER_ADMIN', $roles)) {
            return $this->json(['success' => false, 'message' => 'Accès refusé — non administrateur.'], 403);
        }

        // Vérifier que le compte est actif
        if (!$matchedUser->isActive()) {
            return $this->json(['success' => false, 'message' => 'Compte désactivé.'], 403);
        }

        // ✅ Connecter l'utilisateur directement
        $matchedUser->setLastLogin(new \DateTime());
        $em->flush();

        $userAuthenticator->authenticateUser($matchedUser, $authenticator, $request);

        $this->logger->info('Face login success', [
            'email' => $matchedUser->getEmail(),
            'distance' => $minDistance
        ]);

        return $this->json([
            'success'  => true,
            'message'  => 'Identité confirmée !',
            'redirect' => $this->generateUrl('admin_dashboard'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // API : Vérifie si un email nécessite la face (utilisé par le formulaire login)
    // ─────────────────────────────────────────────────────────────
    #[Route('/admin/face-check', name: 'admin_face_check', methods: ['POST'])]
    public function faceCheck(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json(['requiresFace' => false]);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$user->getFaceDescriptor()) {
            return $this->json(['requiresFace' => false]);
        }

        return $this->json([
            'requiresFace' => true,
            'redirect'     => $this->generateUrl('admin_face_verify'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PAGE : Enregistrement facial (admin déjà connecté)
    // ─────────────────────────────────────────────────────────────
    #[Route('/admin/profile/face-register', name: 'app_profile_face_register')]
    public function faceRegisterPage(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/profile/face_register.html.twig');
    }

    // ─────────────────────────────────────────────────────────────
    // API : Sauvegarde le visage de l'admin connecté
    // ─────────────────────────────────────────────────────────────
    #[Route('/admin/face/register', name: 'face_register', methods: ['POST'])]
    public function registerFace(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (!isset($data['descriptor'], $data['image'])) {
            return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $dir  = $this->params->get('faces_directory');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Supprimer l'ancienne image si elle existe
        if ($user->getFaceImage()) {
            $old = $dir . '/' . basename($user->getFaceImage());
            if (file_exists($old)) unlink($old);
        }

        // Sauvegarder la nouvelle image
        $filename = $user->getId() . '_' . time() . '.jpg';
        $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['image']));
        file_put_contents($dir . '/' . $filename, $img);

        $user->setFaceDescriptor(json_encode($data['descriptor']));
        $user->setFaceImage('uploads/faces/' . $filename);
        $em->flush();

        $this->logger->info('Face registered', ['user_id' => $user->getId()]);

        return $this->json(['success' => true, 'message' => 'Visage enregistré avec succès']);
    }

    // ─────────────────────────────────────────────────────────────
    // API : Supprime le visage de l'admin connecté
    // ─────────────────────────────────────────────────────────────
    #[Route('/admin/face/remove', name: 'face_remove', methods: ['POST'])]
    public function removeFace(EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $this->getUser();

        if ($user->getFaceImage()) {
            $path = $this->params->get('faces_directory') . '/' . basename($user->getFaceImage());
            if (file_exists($path)) unlink($path);
        }

        $user->setFaceDescriptor(null);
        $user->setFaceImage(null);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Reconnaissance faciale désactivée']);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper : distance euclidienne entre deux descripteurs
    // ─────────────────────────────────────────────────────────────
    private function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) return PHP_FLOAT_MAX;

        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $sum += ($a[$i] - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }
}