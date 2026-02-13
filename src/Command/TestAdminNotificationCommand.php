<?php
// src/Command/TestAdminNotificationCommand.php

namespace App\Command;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand(
    name: 'app:test-admin-notification',
    description: 'Test admin notification system by sending a test notification to all admins',
)]
class TestAdminNotificationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private ?HubInterface $hub = null
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🧪 Testing Admin Notification System');

        // Step 1: Find all admins
        $io->section('Step 1: Finding administrators');
        
        try {
            $admins = $this->userRepo->findAdmins();
            $io->success(sprintf('Found %d administrators', count($admins)));
            
            if (empty($admins)) {
                $io->error('No administrators found! Please check:');
                $io->listing([
                    'User table has users with ROLE_ADMIN',
                    'UserRepository::findAdmins() method works correctly'
                ]);
                return Command::FAILURE;
            }
            
            foreach ($admins as $admin) {
                $io->text(sprintf('  - %s (ID: %d, Email: %s)', 
                    $admin->getUsername(), 
                    $admin->getId(), 
                    $admin->getEmail()
                ));
            }
        } catch (\Exception $e) {
            $io->error('Failed to find admins: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 2: Create test notifications
        $io->section('Step 2: Creating test notifications');
        
        $notificationsCreated = [];
        
        foreach ($admins as $admin) {
            try {
                $notification = new Notification();
                $notification->setUser($admin);
                $notification->setType(NotificationType::SYSTEM);
                $notification->setMessage('🧪 TEST: Admin notification system is working!');
                $notification->setLink(null);
                $notification->setIsRead(false);
                $notification->setRead(false);

                $this->em->persist($notification);
                $this->em->flush();
                
                $notificationsCreated[] = [
                    'admin' => $admin,
                    'notification' => $notification
                ];
                
                $io->text(sprintf('  ✅ Created notification ID %d for %s', 
                    $notification->getId(), 
                    $admin->getUsername()
                ));
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to create notification for %s: %s', 
                    $admin->getUsername(), 
                    $e->getMessage()
                ));
            }
        }

        // Step 3: Publish to Mercure
        $io->section('Step 3: Publishing to Mercure Hub');
        
        if (!$this->hub) {
            $io->warning('Mercure Hub is not configured. Notifications created but not published in real-time.');
            $io->text('Check your .env file for MERCURE_URL configuration');
        } else {
            $io->text('Mercure Hub is configured ✓');
            
            foreach ($notificationsCreated as $item) {
                $admin = $item['admin'];
                $notification = $item['notification'];
                
                try {
                    $data = [
                        'id' => $notification->getId(),
                        'message' => $notification->getMessage(),
                        'icon' => $notification->getType()->getIcon(),
                        'link' => $notification->getLink(),
                        'isRead' => $notification->isRead(),
                        'timeAgo' => 'just now',
                        'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s')
                    ];

                    $topic = 'notifications/user/' . $admin->getId();
                    
                    $update = new Update(
                        $topic,
                        json_encode($data)
                    );

                    $this->hub->publish($update);
                    
                    $io->text(sprintf('  ✅ Published to Mercure topic: %s', $topic));
                    $io->text(sprintf('     Data: %s', json_encode($data)));
                } catch (\Exception $e) {
                    $io->error(sprintf('Failed to publish to Mercure for %s: %s', 
                        $admin->getUsername(), 
                        $e->getMessage()
                    ));
                }
            }
        }

        // Summary
        $io->section('Summary');
        $io->success(sprintf('Test completed! %d notifications created.', count($notificationsCreated)));
        
        $io->note([
            'What to check now:',
            '1. Open admin dashboard in browser',
            '2. Check browser console for Mercure connection',
            '3. Look for notification badge and dropdown',
            '4. Check database: SELECT * FROM notification WHERE type = "SYSTEM" ORDER BY created_at DESC LIMIT 5',
        ]);

        return Command::SUCCESS;
    }
}
