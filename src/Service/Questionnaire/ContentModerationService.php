<?php

namespace App\Service\Questionnaire;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ContentModerationService
{
    private HttpClientInterface $httpClient;
    private string $apiUrl;

    public function __construct(HttpClientInterface $httpClient, #[Autowire('%env(default::BAD_WORDS_API_URL)%')] string $apiUrl = 'https://www.purgomalum.com/service/json')
    {
        $this->httpClient = $httpClient;
        $this->apiUrl = $apiUrl;
    }

    /**
     * Check if content contains inappropriate language using API
     */
    public function containsInappropriateContent(string $content): bool
    {
        // First check with static words for testing
        $staticWords = [
            'test', 'motinterdit', 'insulte', 'grossier', 'vulgaire',
            'fuck', 'shit', 'damn', 'bitch', 'ass', 'bastard', 'cunt', 'dick', 'piss', 'hell'
        ];
        
        $contentLower = strtolower($content);
        foreach ($staticWords as $word) {
            if (strpos($contentLower, $word) !== false) {
                return true;
            }
        }
        
        // Then check with API
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl, [
                'query' => [
                    'text' => $content,
                    'fill_text' => '****'
                ]
            ]);

            $data = $response->toArray();
            
            // If the content was modified, it contained inappropriate words
            return $data['result'] !== $content;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Moderate content and return sanitized version using API
     */
    public function moderateContent(string $content): string
    {
        // First replace static words for testing
        $staticWords = [
            'test', 'motinterdit', 'insulte', 'grossier', 'vulgaire',
            'fuck', 'shit', 'damn', 'bitch', 'ass', 'bastard', 'cunt', 'dick', 'piss', 'hell'
        ];
        
        $sanitizedContent = $content;
        foreach ($staticWords as $word) {
            $sanitizedContent = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', '****', $sanitizedContent);
        }
        
        // Then use API for additional words
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl, [
                'query' => [
                    'text' => $sanitizedContent,
                    'fill_text' => '****'
                ]
            ]);

            $data = $response->toArray();
            $sanitizedContent = $data['result'];
            
            // Remove excessive whitespace
            $sanitizedContent = preg_replace('/\s+/', ' ', $sanitizedContent);
            $sanitizedContent = trim($sanitizedContent);
            
            return $sanitizedContent;
        } catch (\Exception $e) {
            // Return static moderation if API fails
            $sanitizedContent = preg_replace('/\s+/', ' ', $sanitizedContent);
            $sanitizedContent = trim($sanitizedContent);
            return $sanitizedContent;
        }
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
