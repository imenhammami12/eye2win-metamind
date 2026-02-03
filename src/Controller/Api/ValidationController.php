<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class ValidationController extends AbstractController
{
    #[Route('/check-username', name: 'check_username', methods: ['POST'])]
    public function checkUsername(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? '';
        
        // Basic validation
        if (empty($username) || strlen($username) < 3) {
            return $this->json([
                'available' => false,
                'message' => 'Username must be at least 3 characters long'
            ]);
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return $this->json([
                'available' => false,
                'message' => 'Username can only contain letters, numbers, and underscores'
            ]);
        }
        
        // Check if username exists in database
        $existingUser = $userRepository->findOneBy(['username' => $username]);
        
        return $this->json([
            'available' => $existingUser === null,
            'message' => $existingUser ? 'This username is already taken' : 'Username is available'
        ]);
    }
    
    #[Route('/check-email', name: 'check_email', methods: ['POST'])]
    public function checkEmail(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        
        // Basic validation
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'available' => false,
                'message' => 'Please provide a valid email address'
            ]);
        }
        
        // Check if email exists in database
        $existingUser = $userRepository->findOneBy(['email' => strtolower(trim($email))]);
        
        return $this->json([
            'available' => $existingUser === null,
            'message' => $existingUser ? 'This email is already registered' : 'Email is available'
        ]);
    }
}