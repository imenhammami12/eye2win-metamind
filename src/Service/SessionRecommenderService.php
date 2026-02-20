<?php

namespace App\Service;

use App\Repository\PlanningRepository;
use Symfony\Bundle\SecurityBundle\Security;

class SessionRecommenderService
{
    private PlanningRepository $planningRepository;
    private Security $security;
    private ?string $groqApiKey;

    public function __construct(PlanningRepository $planningRepository, Security $security)
    {
        $this->planningRepository = $planningRepository;
        $this->security = $security;
        $this->groqApiKey = $_ENV['GROQ_API_KEY'] ?? null;
    }

    public function getRecommendations(): string
    {
        if (!$this->groqApiKey) {
            return "L'IA de recommandation n'est pas configurée.";
        }

        try {
            return $this->generateRecommandations();
        } catch (\Exception $e) {
            return "Désolé, je ne peux pas générer de recommandations pour le moment.";
        }
    }

    private function generateRecommandations(): string
    {
        $user = $this->security->getUser();
        if (!$user) {
            return "Veuillez vous connecter pour recevoir des recommandations personnalisées.";
        }

        $planningContext = $this->buildPlanningContext();
        $userHistoryContext = $this->buildUserHistoryContext($user);

        $url = 'https://api.groq.com/openai/v1/chat/completions';
        
        $systemPrompt = "Tu es un Coach e-sport de haut niveau sur la plateforme EyeTwin.
        Ta mission est d'analyser l'historique de l'utilisateur et de lui recommander la MEILLEURE session prochaine dans le planning.

        CONTEXTE DU PLANNING ACTUEL :
        {$planningContext}

        HISTORIQUE DE L'UTILISATEUR :
        {$userHistoryContext}

        DIRECTIVES :
        1. Sois motivant et utilise un ton de coach professionnel.
        2. Identifie les jeux que l'utilisateur pratique le plus et son niveau habituel.
        3. Choisis 1 ou 2 sessions du planning qui l'aideraient à progresser.
        4. Si l'utilisateur n'a pas d'historique, souhaite-lui la bienvenue et propose-lui une session de découverte selon le planning.
        5. Réponds en français (ou anglais si l'historique suggère une préférence).
        6. Format : Utilise du markdown (gras, listes) pour rendre le conseil lisible.";

        $data = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Analyse mon profil et donne-moi tes recommandations de coaching pour les prochaines sessions."]
            ],
            'temperature' => 0.8,
            'max_tokens' => 800
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->groqApiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return "Désolé, l'IA de coaching rencontre une erreur technique (Code: {$httpCode}).";
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            return "Je n'ai pas pu établir de recommandation spécifique pour le moment.";
        }

        return trim($content);
    }

    private function buildPlanningContext(): string
    {
        $plannings = $this->planningRepository->findAll();
        if (empty($plannings)) {
            return "Aucune session disponible.";
        }

        $context = "SESSIONS DISPONIBLES :\n";
        foreach ($plannings as $p) {
            $date = $p->getDate()->format('d/m/Y');
            $time = $p->getTime()->format('H:i');
            $type = $p->getType()->value;
            $level = $p->getLevel()->value;
            $location = $p->getLocalisation();
            $description = $p->getDescription();
            $participants = count($p->getTrainingSessions());
            
            $context .= "- ID {$p->getId()}: {$type} ({$level}) le {$date} à {$time} @ {$location}. Description: {$description}. Inscrits: {$participants}\n";
        }
        return $context;
    }

    private function buildUserHistoryContext($user): string
    {
        $sessions = $user->getTrainingSessions();
        if ($sessions->isEmpty()) {
            return "Aucun historique.";
        }

        $context = "";
        foreach ($sessions as $s) {
            $p = $s->getPlanning();
            $context .= "- A participé à : {$p->getType()->value} ({$p->getLevel()->value}) le {$p->getDate()->format('d/m/Y')}\n";
        }
        return $context;
    }
}
