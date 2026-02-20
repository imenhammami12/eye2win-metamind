<?php

namespace App\Service;

use App\Repository\PlanningRepository;

class ChatbotService
{
    private PlanningRepository $planningRepository;
    private ?string $groqApiKey;

    public function __construct(PlanningRepository $planningRepository)
    {
        $this->planningRepository = $planningRepository;
        // Get API key from environment variable
        $this->groqApiKey = $_ENV['GROQ_API_KEY'] ?? null;
    }

    public function getResponse(string $question): string
    {
        $question = trim($question);
        $questionLower = strtolower($question);
        
        // Priorité absolue à l'IA avec le contexte de la base de données
        if ($this->groqApiKey) {
            try {
                return $this->getGroqResponse($question);
            } catch (\Exception $e) {
                // En cas d'échec de l'API, on utilise les règles en dernier recours
                return $this->getRuleBasedResponse($questionLower);
            }
        }
        
        // Fallback si pas de clé API
        return $this->getRuleBasedResponse($questionLower);
    }

    private function isPlanningQuestion(string $question): bool
    {
        $planningKeywords = [
            'session', 'planning', 'entraînement', 'training', 'disponible', 'available',
            'prochaine', 'next', 'quand', 'when', 'type', 'genre', 'niveau', 'level',
            'localisation', 'location', 'où', 'where', 'rejoindre', 'join', 'participer',
            'inscription', 'horaire', 'schedule', 'date', 'heure', 'time'
        ];
        
        foreach ($planningKeywords as $keyword) {
            if (strpos($question, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function getGroqResponse(string $question): string
    {
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        
        $lang = $this->detectLanguage($question);
        $languageName = ($lang === 'en') ? 'English' : 'French';
        $forcedInstruction = ($lang === 'en') 
            ? "Respond strictly in English." 
            : "Réponds strictement en français.";
        
        // Build context about planning sessions
        $planningContext = $this->buildPlanningContext();
        
        $systemPrompt = "Tu es un assistant virtuel expert en e-sport pour la plateforme EyeTwin.
        
        CONTEXTE DES SESSIONS (données réelles de la base de données) :
        {$planningContext}
        
        DIRECTIVES :
        1. Utilise UNIQUEMENT les données fournies ci-dessus pour répondre aux questions sur le planning.
        2. Si une session n'est pas dans la liste, elle n'existe pas.
        3. Réponds aux questions sur l'e-sport en général (stratégies, jeux, etc.) de manière professionnelle.
        4. Si l'utilisateur pose une question hors sujet (pas d'e-sport, pas de planning), refuse poliment.
        5. Réponds TOUJOURS dans la même langue que l'utilisateur ({$languageName}).
        6. Sois précis sur les détails : date, heure, lieu, niveau, et description.";

        $data = [
            'model' => 'llama-3.3-70b-versatile', // Fast and powerful free model
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $question
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500,
            'top_p' => 1,
            'stream' => false
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
            throw new \Exception('Groq API request failed');
        }

        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        }

        throw new \Exception('Invalid Groq API response');
    }

    private function buildPlanningContext(): string
    {
        $plannings = $this->planningRepository->findAllOrderedByDate('ASC');
        
        if (empty($plannings)) {
            return "Aucune session d'entraînement n'est disponible pour le moment.";
        }
        
        $context = "Liste exhaustive des sessions d'entraînement :\n";
        
        foreach ($plannings as $planning) {
            $date = $planning->getDate()->format('d/m/Y');
            $time = $planning->getTime()->format('H:i');
            $type = $planning->getType()->value;
            $level = $planning->getLevel()->value;
            $location = $planning->getLocalisation();
            $description = $planning->getDescription();
            $partner = $planning->isNeedPartner() ? "Partenaire requis" : "Pas de partenaire nécessaire";
            $participantsCount = count($planning->getTrainingSessions());
            
            $context .= "- SESSION CODÉE #{$planning->getId()} :\n";
            $context .= "  Type : {$type}\n";
            $context .= "  Niveau : {$level}\n";
            $context .= "  Date : {$date} à {$time}\n";
            $context .= "  Lieu : {$location}\n";
            $context .= "  Description : {$description}\n";
            $context .= "  Contrainte : {$partner}\n";
            $context .= "  Inscrits actuels : {$participantsCount}\n\n";
        }
        
        return $context;
    }

    private function detectLanguage(string $text): string
    {
        $textLower = strtolower($text);
        
        // Distinct English words (particles, pronouns, verbs, and common indicators)
        $englishPattern = '/\b(the|a|an|is|are|am|i|you|he|she|they|we|what|whats|what\'s|where|how|why|when|which|who|whom|whose|this|that|these|those|at|on|in|with|from|by|for|to|of|and|or|not|no|yes|do|does|did|will|would|can|could|shall|should|may|might|must|have|has|had|getting|going|about|my|your|his|her|its|our|their|be|been|being|game|gaming|esport)\b/i';
        
        // Distinct French words
        $frenchPattern = '/\b(le|la|les|un|une|des|est|sont|suis|je|tu|il|elle|ils|elles|nous|vous|pourquoi|comment|quand|que|qui|quel|quelle|quels|quelles|où|dans|sur|avec|pour|par|mais|ou|et|ne|pas|oui|non|faire|fait|faites|font|aider|aide|veux|veut|voulons|voulez|veulent|ce|cette|ces|mon|ton|son|notre|votre|leur|mes|tes|ses|nos|vos|leurs|être|été|en|jeu|jeux|esport)\b/i';
        
        $enMatches = preg_match_all($englishPattern, $textLower);
        $frMatches = preg_match_all($frenchPattern, $textLower);
        
        if ($enMatches > $frMatches) {
            return 'en';
        } elseif ($frMatches > $enMatches) {
            return 'fr';
        }
        
        // Secondary check for common e-sport terms if still ambiguous or zero matches
        $enTerms = ['training', 'available', 'next', 'improve', 'gameplay', 'gaming', 'esport', 'join', 'help', 'hello', 'hi', 'thanks'];
        $frTerms = ['entraînement', 'disponible', 'prochaine', 'améliorer', 'rejoindre', 'salut', 'merci', 'aide', 'bonjour'];
        
        $enCount = 0;
        foreach ($enTerms as $term) {
            if (strpos($textLower, $term) !== false) $enCount++;
        }
        
        $frCount = 0;
        foreach ($frTerms as $term) {
            if (strpos($textLower, $term) !== false) $frCount++;
        }
        
        if ($enCount > $frCount) return 'en';
        if ($frCount > $enCount) return 'fr';

        // Final check: if the word "game" appears anywhere, it's likely English in this context
        if (strpos($textLower, 'game') !== false) return 'en';

        // Default to French
        return 'fr';
    }

    private function getRuleBasedResponse(string $question): string
    {
        $lang = $this->detectLanguage($question);

        // Planning-related questions
        if ($this->matchesPattern($question, ['session', 'planning', 'entraînement', 'training', 'disponible', 'available'])) {
            return $this->getAvailableSessionsResponse($lang);
        }
        
        if ($this->matchesPattern($question, ['prochaine', 'next', 'quand', 'when'])) {
            return $this->getNextSessionResponse($lang);
        }
        
        if ($this->matchesPattern($question, ['type', 'genre', 'kind'])) {
            return $this->getSessionTypesResponse($lang);
        }
        
        if ($this->matchesPattern($question, ['niveau', 'level', 'difficulté', 'difficulty'])) {
            return $this->getSessionLevelsResponse($lang);
        }
        
        if ($this->matchesPattern($question, ['localisation', 'location', 'où', 'where'])) {
            return $this->getLocationsResponse($lang);
        }
        
        if ($this->matchesPattern($question, ['rejoindre', 'join', 'participer', 'participate', 'inscription'])) {
            return $lang === 'fr' 
                ? "Pour rejoindre une session, cliquez simplement sur le bouton 'Join Session' sur la carte de la session qui vous intéresse. Vous devez être connecté pour vous inscrire."
                : "To join a session, simply click the 'Join Session' button on the card of the session you're interested in. You must be logged in to register.";
        }
        
        // E-sports general questions (basic fallback)
        if ($this->matchesPattern($question, ['e-sport', 'esport', 'c\'est quoi', 'what is', 'définition', 'definition'])) {
            return $lang === 'fr'
                ? "L'e-sport (sport électronique) désigne la pratique compétitive de jeux vidéo, souvent organisée en tournois professionnels. C'est une discipline qui combine réflexes, stratégie et travail d'équipe."
                : "E-sports (electronic sports) refers to the competitive practice of video games, often organized into professional tournaments. It is a discipline that combines reflexes, strategy, and teamwork.";
        }
        
        if ($this->matchesPattern($question, ['jeux', 'games', 'populaire', 'popular', 'quels'])) {
            return $lang === 'fr'
                ? "Les jeux e-sport les plus populaires incluent : League of Legends, Counter-Strike 2, Dota 2, Valorant, Fortnite, Rocket League, Overwatch 2, et Rainbow Six Siege. Chacun a sa propre scène compétitive avec des tournois majeurs."
                : "The most popular e-sports games include: League of Legends, Counter-Strike 2, Dota 2, Valorant, Fortnite, Rocket League, Overwatch 2, and Rainbow Six Siege. Each has its own competitive scene with major tournaments.";
        }
        
        if ($this->matchesPattern($question, ['améliorer', 'improve', 'progresser', 'progress', 'mieux', 'better', 'conseil', 'tips'])) {
            return $lang === 'fr'
                ? "Pour progresser en e-sport : 1) Entraînez-vous régulièrement avec des objectifs précis, 2) Analysez vos parties pour identifier vos erreurs, 3) Regardez des joueurs professionnels et apprenez leurs stratégies, 4) Travaillez votre communication d'équipe, 5) Maintenez une bonne hygiène de vie (sommeil, alimentation), 6) Rejoignez nos sessions d'entraînement pour pratiquer avec d'autres joueurs !"
                : "To progress in e-sports: 1) Train regularly with specific goals, 2) Analyze your games to identify your mistakes, 3) Watch professional players and learn their strategies, 4) Work on your team communication, 5) Maintain a healthy lifestyle (sleep, diet), 6) Join our training sessions to practice with other players!";
        }
        
        if ($this->matchesPattern($question, ['carrière', 'career', 'professionnel', 'professional', 'pro'])) {
            return $lang === 'fr'
                ? "Devenir joueur pro demande beaucoup de dévouement. Commencez par participer à des tournois amateurs, rejoignez une équipe, entraînez-vous quotidiennement (4-8h), construisez votre présence en ligne (streaming, réseaux sociaux), et participez à des ligues compétitives. Nos sessions peuvent vous aider à débuter votre parcours !"
                : "Becoming a pro player takes a lot of dedication. Start by participating in amateur tournaments, join a team, train daily (4-8h), build your online presence (streaming, social media), and participate in competitive leagues. Our sessions can help you start your journey!";
        }
        
        if ($this->matchesPattern($question, ['équipe', 'team', 'partenaire', 'partner', 'teammate'])) {
            return $lang === 'fr'
                ? "Certaines de nos sessions nécessitent un partenaire (indiqué par le badge 'Partner Needed'). C'est une excellente opportunité pour rencontrer d'autres joueurs et former une équipe !"
                : "Some of our sessions require a partner (indicated by the 'Partner Needed' badge). This is a great opportunity to meet other players and form a team!";
        }
        
        if ($this->matchesPattern($question, ['coach', 'entraîneur', 'mentor'])) {
            return $lang === 'fr'
                ? "Nos sessions sont encadrées par des coachs expérimentés qui peuvent vous aider à améliorer vos compétences. Consultez les détails de chaque session pour en savoir plus sur les entraîneurs."
                : "Our sessions are led by experienced coaches who can help you improve your skills. Check the details of each session to learn more about the coaches.";
        }
        
        // Greetings
        if ($this->matchesPattern($question, ['bonjour', 'salut', 'hello', 'hi', 'hey'])) {
            return $lang === 'fr'
                ? "Bonjour ! 👋 Je suis votre assistant virtuel spécialisé en e-sport. Je peux vous aider avec des questions sur nos sessions d'entraînement et l'e-sport en général (jeux, stratégies, tournois, conseils, etc.). Comment puis-je vous aider ?"
                : "Hello! 👋 I am your virtual assistant specialized in e-sports. I can help you with questions about our training sessions and e-sports in general (games, strategies, tournaments, tips, etc.). How can I help you?";
        }
        
        if ($this->matchesPattern($question, ['merci', 'thank', 'thanks'])) {
            return $lang === 'fr'
                ? "De rien ! N'hésitez pas si vous avez d'autres questions sur l'e-sport ou nos sessions. 😊"
                : "You're welcome! Feel free to ask if you have any other questions about e-sports or our sessions. 😊";
        }
        
        if ($this->matchesPattern($question, ['aide', 'help', 'aidez-moi'])) {
            return $lang === 'fr'
                ? "Je peux vous aider avec :\n- Les sessions d'entraînement disponibles\n- Les horaires et localisations\n- Les types et niveaux de sessions\n- Des conseils sur l'e-sport (stratégies, amélioration, carrière)\n- Des informations sur les jeux compétitifs\n- Comment rejoindre une session\n\nPosez-moi une question !"
                : "I can help you with:\n- Available training sessions\n- Schedules and locations\n- Types and levels of sessions\n- E-sports tips (strategies, improvement, career)\n- Information on competitive games\n- How to join a session\n\nAsk me a question!";
        }
        
        // Default response
        return $lang === 'fr'
            ? "Je suis un assistant spécialisé uniquement dans l'e-sport et nos sessions d'entraînement. Posez-moi votre question sur ces sujets et je ferai de mon mieux pour vous répondre !"
            : "I am an assistant specialized only in e-sports and our training sessions. Ask me your question on these topics and I will do my best to answer you!";
    }

    private function matchesPattern(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getAvailableSessionsResponse(string $lang): string
    {
        $plannings = $this->planningRepository->findAll();
        
        if (empty($plannings)) {
            return $lang === 'fr'
                ? "Aucune session n'est disponible pour le moment. Revenez plus tard pour découvrir nos prochaines sessions !"
                : "No sessions are available at the moment. Come back later to discover our next sessions!";
        }
        
        $count = count($plannings);
        $response = $lang === 'fr'
            ? "Nous avons actuellement {$count} session(s) d'entraînement disponible(s) :\n\n"
            : "We currently have {$count} training session(s) available:\n\n";
        
        $limit = min(3, $count);
        for ($i = 0; $i < $limit; $i++) {
            $planning = $plannings[$i];
            $date = $planning->getDate()->format('d/m/Y');
            $time = $planning->getTime()->format('H:i');
            $type = $planning->getType()->value;
            $response .= "• {$type} - {$date} " . ($lang === 'fr' ? 'à' : 'at') . " {$time}\n";
        }
        
        if ($count > 3) {
            $response .= $lang === 'fr'
                ? "\n... et " . ($count - 3) . " autre(s) session(s). Consultez la liste complète ci-dessus !"
                : "\n... and " . ($count - 3) . " other session(s). Check the full list above!";
        }
        
        return $response;
    }

    private function getNextSessionResponse(string $lang): string
    {
        $plannings = $this->planningRepository->createQueryBuilder('p')
            ->where('p.date >= :today')
            ->setParameter('today', new \DateTime())
            ->orderBy('p.date', 'ASC')
            ->addOrderBy('p.time', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
        
        if (empty($plannings)) {
            return $lang === 'fr'
                ? "Aucune session n'est prévue pour le moment. Consultez régulièrement cette page pour les nouvelles sessions !"
                : "No sessions are scheduled at the moment. Check this page regularly for new sessions!";
        }
        
        $planning = $plannings[0];
        $date = $planning->getDate()->format('d/m/Y');
        $time = $planning->getTime()->format('H:i');
        $type = $planning->getType()->value;
        $location = $planning->getLocalisation();
        
        return $lang === 'fr'
            ? "La prochaine session est un entraînement {$type} le {$date} à {$time}, à {$location}. Cliquez sur 'Join Session' pour vous inscrire !"
            : "The next session is a {$type} training on {$date} at {$time}, in {$location}. Click 'Join Session' to register!";
    }

    private function getSessionTypesResponse(string $lang): string
    {
        $plannings = $this->planningRepository->findAll();
        
        if (empty($plannings)) {
            return $lang === 'fr' ? "Aucune session disponible pour le moment." : "No sessions available at the moment.";
        }
        
        $types = [];
        foreach ($plannings as $planning) {
            $typeValue = $planning->getType()->value;
            if (!in_array($typeValue, $types)) {
                $types[] = $typeValue;
            }
        }
        
        if (empty($types)) {
            return $lang === 'fr'
                ? "Nous proposons différents types de sessions d'entraînement. Consultez la liste ci-dessus pour plus de détails."
                : "We offer different types of training sessions. Check the list above for more details.";
        }
        
        return $lang === 'fr'
            ? "Nous proposons actuellement ces types de sessions : " . implode(', ', $types) . ". Chaque type est adapté à différents objectifs d'entraînement."
            : "We currently offer these types of sessions: " . implode(', ', $types) . ". Each type is adapted to different training goals.";
    }

    private function getSessionLevelsResponse(string $lang): string
    {
        return $lang === 'fr'
            ? "Nos sessions sont disponibles pour tous les niveaux : Débutant, Intermédiaire et Avancé. Chaque session indique le niveau requis avec un badge. Choisissez celle qui correspond à votre expérience !"
            : "Our sessions are available for all levels: Beginner, Intermediate, and Advanced. Each session indicates the required level with a badge. Choose the one that matches your experience!";
    }

    private function getLocationsResponse(string $lang): string
    {
        $plannings = $this->planningRepository->findAll();
        
        if (empty($plannings)) {
            return $lang === 'fr' ? "Aucune session disponible pour le moment." : "No sessions available at the moment.";
        }
        
        $locations = [];
        foreach ($plannings as $planning) {
            $loc = $planning->getLocalisation();
            if (!in_array($loc, $locations)) {
                $locations[] = $loc;
            }
        }
        
        if (empty($locations)) {
            return $lang === 'fr'
                ? "Les localisations varient selon les sessions. Consultez les détails de chaque session ci-dessus."
                : "Locations vary by session. Check the details of each session above.";
        }
        
        return $lang === 'fr'
            ? "Nos sessions ont lieu à : " . implode(', ', $locations) . ". Consultez chaque session pour connaître le lieu exact."
            : "Our sessions take place at: " . implode(', ', $locations) . ". Check each session for the exact location.";
    }
}
