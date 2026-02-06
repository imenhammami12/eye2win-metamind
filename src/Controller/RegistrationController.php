<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\AccountStatus;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): Response {
        // If user is already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Get form data
            $plainPassword = $form->get('plainPassword')->getData();
            $agreeTerms = $form->get('agreeTerms')->getData();
            
            // ===== BACKEND VALIDATIONS =====
            
            // 1. Username validation
            if (empty($user->getUsername()) || strlen($user->getUsername()) < 3) {
                $this->addFlash('error', 'Username must contain at least 3 characters.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // Username format validation (alphanumeric and underscore only)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $user->getUsername())) {
                $this->addFlash('error', 'Username can only contain letters, numbers, and underscores.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 2. Email validation
            if (empty($user->getEmail()) || !filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please provide a valid email address.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 3. Full name validation
            if (empty($user->getFullName()) || strlen($user->getFullName()) < 2) {
                $this->addFlash('error', 'Full name must contain at least 2 characters.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 4. Password length validation
            if (empty($plainPassword) || strlen($plainPassword) < 6) {
                $this->addFlash('error', 'Password must contain at least 6 characters.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 5. Password complexity validation 
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $plainPassword)) {
                $this->addFlash('error', 'Password must contain at least one uppercase letter, one lowercase letter, and one number.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 6. Terms acceptance validation
            if (!$agreeTerms) {
                $this->addFlash('error', 'You must accept the terms and conditions.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 7. Username uniqueness check
            $existingUserByUsername = $entityManager->getRepository(User::class)
                ->findOneBy(['username' => $user->getUsername()]);
            
            if ($existingUserByUsername) {
                $this->addFlash('error', 'This username is already taken.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 8. Email uniqueness and account status check
            $existingUserByEmail = $entityManager->getRepository(User::class)
                ->findOneBy(['email' => $user->getEmail()]);
            
            if ($existingUserByEmail) {
                // Check if the existing account is banned or suspended
                if ($existingUserByEmail->getAccountStatus() === AccountStatus::BANNED) {
                    $this->addFlash('error', 'This email address is associated with a banned account. You cannot register with this email.');
                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form->createView(),
                    ]);
                }
                
                if ($existingUserByEmail->getAccountStatus() === AccountStatus::SUSPENDED) {
                    $this->addFlash('error', 'This email address is associated with a suspended account. Please contact support to resolve this issue.');
                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form->createView(),
                    ]);
                }
                
                $this->addFlash('error', 'This email address is already registered.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // 9. Symfony validator (entity constraints)
            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // ===== IF ALL VALIDATIONS PASS =====
            
            try {
                // Hash password
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $plainPassword
                    )
                );

                // Default role
                $user->setRoles(['ROLE_USER']);
                
                // Data sanitization
                $user->setUsername(trim($user->getUsername()));
                $user->setEmail(strtolower(trim($user->getEmail())));
                $user->setFullName(trim($user->getFullName()));

                $entityManager->persist($user);
                $entityManager->flush();

                // Success message
                $this->addFlash('success', 'Account created successfully! You can now log in.');

                // Redirect to login page
                return $this->redirectToRoute('app_login');
                
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                // Unique constraint violation (duplicate entry)
                $this->addFlash('error', 'An account already exists with this information.');
            } catch (\Exception $e) {
                // Any other error during registration
                $this->addFlash('error', 'An error occurred while creating your account. Please try again.');
                
                // Error logging for debugging (optional)
                // $this->logger->error('Registration error: ' . $e->getMessage());
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}