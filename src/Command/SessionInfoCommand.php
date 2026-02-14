<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:session:info',
    description: 'Affiche les informations sur la configuration et le stockage des sessions',
)]
class SessionInfoCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('📊 Configuration des Sessions');

        // 1. Configuration PHP
        $io->section('Configuration PHP (php.ini)');
        $io->table(
            ['Paramètre', 'Valeur'],
            [
                ['session.save_handler', ini_get('session.save_handler')],
                ['session.save_path', ini_get('session.save_path')],
                ['session.name', ini_get('session.name')],
                ['session.cookie_lifetime', ini_get('session.cookie_lifetime') . ' secondes'],
                ['session.gc_maxlifetime', ini_get('session.gc_maxlifetime') . ' secondes (' . round(ini_get('session.gc_maxlifetime') / 3600, 2) . ' heures)'],
                ['session.cookie_secure', ini_get('session.cookie_secure') ? 'Oui (HTTPS uniquement)' : 'Non'],
                ['session.cookie_httponly', ini_get('session.cookie_httponly') ? 'Oui' : 'Non'],
                ['session.cookie_samesite', ini_get('session.cookie_samesite') ?: 'Non défini'],
            ]
        );

        // 2. Configuration Symfony
        $io->section('Configuration Symfony');
        
        $sessionSavePath = ini_get('session.save_path');
        if (empty($sessionSavePath)) {
            $sessionSavePath = sys_get_temp_dir();
        }
        
        $io->text([
            "Handler: <info>" . ini_get('session.save_handler') . "</info>",
            "Chemin de stockage: <info>$sessionSavePath</info>",
        ]);

        // 3. Sessions actives
        $io->section('Sessions actives sur le disque');
        
        try {
            $finder = new Finder();
            $finder->files()->in($sessionSavePath)->name('sess_*');
            
            if ($finder->hasResults()) {
                $sessionCount = 0;
                $sessionsData = [];
                
                foreach ($finder as $file) {
                    $sessionCount++;
                    $fileName = $file->getFilename();
                    $sessionId = str_replace('sess_', '', $fileName);
                    $size = $file->getSize();
                    $mtime = $file->getMTime();
                    $age = time() - $mtime;
                    
                    $sessionsData[] = [
                        substr($sessionId, 0, 20) . '...',
                        $this->formatBytes($size),
                        date('d/m/Y H:i:s', $mtime),
                        $this->formatDuration($age),
                    ];
                    
                    if ($sessionCount >= 10) {
                        break; // Limiter à 10 pour ne pas surcharger
                    }
                }
                
                $io->table(
                    ['Session ID', 'Taille', 'Dernière modif', 'Âge'],
                    $sessionsData
                );
                
                $totalSessions = iterator_count($finder);
                $io->success("Total de sessions trouvées: $totalSessions");
                
                if ($totalSessions > 10) {
                    $io->note("Affichage limité aux 10 premières sessions");
                }
            } else {
                $io->warning("Aucune session trouvée dans $sessionSavePath");
            }
        } catch (\Exception $e) {
            $io->error("Impossible d'accéder au répertoire de sessions: " . $e->getMessage());
        }

        // 4. Variables de session pour reconnaissance faciale
        $io->section('Variables de session utilisées pour la reconnaissance faciale');
        $io->listing([
            'face_pre_login_verified (bool) - Face vérifiée?',
            'face_pre_login_verified_user_id (int) - ID utilisateur reconnu',
            'face_pre_login_verified_email (string) - Email utilisateur reconnu',
            'face_pre_login_user_id (int) - ID utilisateur temporaire',
            'face_pre_login_email (string) - Email temporaire',
        ]);

        // 5. Recommandations
        $io->section('💡 Recommandations');
        
        $gcMaxLifetime = (int)ini_get('session.gc_maxlifetime');
        if ($gcMaxLifetime < 3600) {
            $io->warning("⚠️  session.gc_maxlifetime est très court ($gcMaxLifetime secondes). Les sessions de reconnaissance faciale pourraient expirer rapidement.");
        }
        
        if (!ini_get('session.cookie_httponly')) {
            $io->warning("⚠️  session.cookie_httponly est désactivé. Activer pour plus de sécurité.");
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' sec';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' min';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' heures';
        }
        return round($seconds / 86400, 1) . ' jours';
    }
}
