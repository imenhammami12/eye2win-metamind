<?php
/**
 * Debug script to help troubleshoot video upload issues
 * Run this from the Symfony console
 */

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\FormFactoryInterface;
use App\Form\VideoUploadType;
use App\Form\AdminVideoUploadType;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DebugVideoUploadCommand extends Command
{
    protected static $defaultName = 'debug:video-upload';
    
    public function __construct(
        private FormFactoryInterface $formFactory,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Video Upload Debug Information');
        $output->writeln('==============================');
        $output->writeln('');
        
        // Check CSRF token generation
        $output->writeln('1. CSRF Token Generation');
        try {
            $token = $this->csrfTokenManager->getToken('video_upload');
            $output->writeln('   ✓ CSRF Token generated: ' . substr($token->getValue(), 0, 20) . '...');
        } catch (\Exception $e) {
            $output->writeln('   ✗ CSRF Token generation failed: ' . $e->getMessage());
        }
        
        // Check form creation
        $output->writeln('');
        $output->writeln('2. Form Creation Test');
        try {
            $form = $this->formFactory->create(VideoUploadType::class);
            $output->writeln('   ✓ VideoUploadType form created successfully');
            
            // Check form fields
            $output->writeln('   Form fields:');
            foreach ($form as $field) {
                $output->writeln('     - ' . $field->getName() . ' (' . get_class($field->getConfig()->getType()->getInnerType()) . ')');
            }
        } catch (\Exception $e) {
            $output->writeln('   ✗ Form creation failed: ' . $e->getMessage());
        }
        
        try {
            $form = $this->formFactory->create(AdminVideoUploadType::class);
            $output->writeln('   ✓ AdminVideoUploadType form created successfully');
            
            // Check form fields
            $output->writeln('   Form fields:');
            foreach ($form as $field) {
                $output->writeln('     - ' . $field->getName() . ' (' . get_class($field->getConfig()->getType()->getInnerType()) . ')');
            }
        } catch (\Exception $e) {
            $output->writeln('   ✗ Form creation failed: ' . $e->getMessage());
        }
        
        $output->writeln('');
        $output->writeln('3. Configuration Summary');
        $output->writeln('   - Cloudinary Cloud Name: ' . (getenv('CLOUDINARY_CLOUD_NAME') ? 'SET' : 'NOT SET'));
        $output->writeln('   - Cloudinary API Key: ' . (getenv('CLOUDINARY_API_KEY') ? 'SET' : 'NOT SET'));
        $output->writeln('   - Cloudinary API Secret: ' . (getenv('CLOUDINARY_API_SECRET') ? 'SET' : 'NOT SET'));
        $output->writeln('   - Database URL: ' . (getenv('DATABASE_URL') ? 'SET' : 'NOT SET'));
        
        return Command::SUCCESS;
    }
}
