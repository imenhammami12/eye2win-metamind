<?php

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test/admins', name: 'test_admins')]
    public function testAdmins(NotificationService $notificationService): Response
    {
        // Call private method via reflection to test
        $reflection = new \ReflectionClass($notificationService);
        $method = $reflection->getMethod('getAdmins');
        $method->setAccessible(true);
        $admins = $method->invoke($notificationService);
        
        dump('Admins trouvés:', $admins);
        
        foreach ($admins as $admin) {
            dump([
                'ID' => $admin->getId(),
                'Username' => $admin->getUsername(),
                'Email' => $admin->getEmail(),
                'Roles' => $admin->getRoles()
            ]);
        }
        
        return new Response('Check the profiler!');
    }
}