<?php

namespace App\Service\Questionnaire;

class ContentModerationService
{
    private array $badWords;
    private array $badWordsRegex;

    public function __construct()
    {
        $this->initializeWordLists();
    }

    /**
     * Initialise les listes de mots interdits
     */
    private function initializeWordLists(): void
    {
        // Liste des bad words en français et anglais
        $this->badWords = [
            // Français
            'merde', 'pute', 'salope', 'connard', 'enculé', 'fdp', 'ntm', 'bordel',
            'couilles', 'bite', 'chatte', 'salaud', 'enculer', 'putain', 'fuck',
            'chier', 'con', 'connasse', 'trouduc', 'tafiole', 'batard',
            // Anglais
            'fuck', 'shit', 'asshole', 'bitch', 'cunt', 'dick', 'pussy', 'whore',
            'bastard', 'damn', 'hell', 'crap', 'suck', 'wanker'
        ];

        // Crée les expressions régulières pour détecter les variations
        $this->badWordsRegex = [];
        foreach ($this->badWords as $word) {
            // Pattern pour détecter les variations avec caractères spéciaux
            $pattern = '/' . preg_quote($word, '/') . '/i';
            $this->badWordsRegex[] = $pattern;
            
            // Pattern pour détecter les mots avec caractères substitués (ex: f@ck, sh1t)
            $leetPattern = $this->createLeetPattern($word);
            if ($leetPattern) {
                $this->badWordsRegex[] = $leetPattern;
            }
        }
    }

    /**
     * Crée un pattern regex pour détecter les variations leet speak
     */
    private function createLeetPattern(string $word): ?string
    {
        $leetMap = [
            'a' => '[a4@]',
            'e' => '[e3]',
            'i' => '[i1!]',
            'o' => '[o0]',
            's' => '[s5$]',
            't' => '[t7]',
            'l' => '[l1]',
            'c' => '[c(]',
            'u' => '[u]',
            'k' => '[k]',
            'b' => '[b8]',
            'h' => '[h]',
            'w' => '[w]',
            'r' => '[r]',
            'd' => '[d]',
            'n' => '[n]',
            'p' => '[p9]',
            'f' => '[f]',
            'm' => '[m]'
        ];

        $pattern = '/';
        $chars = str_split(strtolower($word));
        
        foreach ($chars as $char) {
            if (isset($leetMap[$char])) {
                $pattern .= $leetMap[$char];
            } else {
                $pattern .= preg_quote($char, '/');
            }
        }
        $pattern .= '/i';

        return $pattern;
    }

    /**
     * Vérifie si le texte contient des bad words
     */
    public function containsBadWords(string $text): bool
    {
        foreach ($this->badWordsRegex as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Filtre les bad words en les remplaçant par ****
     */
    public function filterBadWords(string $text): string
    {
        $filteredText = $text;
        
        foreach ($this->badWordsRegex as $pattern) {
            $filteredText = preg_replace_callback($pattern, function($matches) {
                return str_repeat('*', strlen($matches[0]));
            }, $filteredText);
        }
        
        return $filteredText;
    }

    /**
     * Ajoute un nouveau bad word à la liste
     */
    public function addBadWord(string $word): void
    {
        $word = strtolower(trim($word));
        if (!in_array($word, $this->badWords)) {
            $this->badWords[] = $word;
            
            // Recrée les patterns regex
            $this->badWordsRegex = [];
            foreach ($this->badWords as $w) {
                $pattern = '/' . preg_quote($w, '/') . '/i';
                $this->badWordsRegex[] = $pattern;
                
                $leetPattern = $this->createLeetPattern($w);
                if ($leetPattern) {
                    $this->badWordsRegex[] = $leetPattern;
                }
            }
        }
    }

    /**
     * Retourne la liste des bad words
     */
    public function getBadWords(): array
    {
        return $this->badWords;
    }
}
