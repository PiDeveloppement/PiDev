<?php

namespace App\Service\Questionnaire;

class ContentModerationService
{
    /**
     * Check if content contains inappropriate language
     */
    public function containsInappropriateContent(string $content): bool
    {
        // List of inappropriate words (can be expanded)
        $inappropriateWords = [
            'spam', 'scam', 'abuse', 'hate', 'violence'
        ];

        $contentLower = strtolower($content);
        foreach ($inappropriateWords as $word) {
            if (strpos($contentLower, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Moderate content and return sanitized version
     */
    public function moderateContent(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim the content
        $content = trim($content);
        
        return $content;
    }

    /**
     * Check if content is too short
     */
    public function isTooShort(string $content, int $minLength = 3): bool
    {
        return strlen(trim($content)) < $minLength;
    }

    /**
     * Check if content is too long
     */
    public function isTooLong(string $content, int $maxLength = 1000): bool
    {
        return strlen($content) > $maxLength;
    }
}