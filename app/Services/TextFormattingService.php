<?php

namespace App\Services;

use Illuminate\Support\Str;

class TextFormattingService
{
    /**
     * Format and sanitize text content.
     */
    public function format(string $content): string
    {
        // Remove potentially harmful HTML tags and scripts
        $content = $this->sanitizeHtml($content);
        
        // Process markdown-style formatting
        $content = $this->processMarkdown($content);
        
        // Process mentions and hashtags
        $content = $this->processAtMentions($content);
        $content = $this->processHashtags($content);
        
        // Process URLs and make them clickable
        $content = $this->processUrls($content);
        
        // Clean up extra whitespace
        $content = $this->cleanWhitespace($content);
        
        return $content;
    }

    /**
     * Sanitize HTML content.
     */
    protected function sanitizeHtml(string $content): string
    {
        // Allow basic formatting tags but remove dangerous ones
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><a><span>';
        
        $content = strip_tags($content, $allowedTags);
        
        // Remove javascript: and data: schemes from links
        $content = preg_replace('/href\s*=\s*["\'](?:javascript|data):/i', 'href="#"', $content);
        
        // Remove event handlers
        $content = preg_replace('/\s*on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $content);
        
        return $content;
    }

    /**
     * Process markdown-style formatting.
     */
    protected function processMarkdown(string $content): string
    {
        // Bold text: **text** or __text__
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $content);
        
        // Italic text: *text* or _text_
        $content = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $content);
        $content = preg_replace('/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', $content);
        
        // Strikethrough: ~~text~~
        $content = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $content);
        
        // Code: `text`
        $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
        
        // Line breaks: Convert double newlines to paragraphs
        $content = preg_replace('/\n\n+/', '</p><p>', $content);
        $content = '<p>' . $content . '</p>';
        
        // Single line breaks
        $content = preg_replace('/\n/', '<br>', $content);
        
        // Clean up empty paragraphs
        $content = preg_replace('/<p><\/p>/', '', $content);
        
        return $content;
    }

    /**
     * Process @mentions.
     */
    protected function processAtMentions(string $content): string
    {
        // Match @username patterns
        $pattern = '/@([a-zA-Z0-9_]+)/';
        
        $content = preg_replace_callback($pattern, function ($matches) {
            $username = $matches[1];
            
            // Here you could check if the user exists and create proper links
            // For now, just wrap in a span with a class for styling
            return '<span class="mention" data-username="' . $username . '">@' . $username . '</span>';
        }, $content);
        
        return $content;
    }

    /**
     * Process #hashtags.
     */
    protected function processHashtags(string $content): string
    {
        // Match #hashtag patterns
        $pattern = '/#([a-zA-Z0-9_]+)/';
        
        $content = preg_replace_callback($pattern, function ($matches) {
            $hashtag = $matches[1];
            
            // Wrap in a span with a class for styling and future functionality
            return '<span class="hashtag" data-hashtag="' . $hashtag . '">#' . $hashtag . '</span>';
        }, $content);
        
        return $content;
    }

    /**
     * Process URLs and make them clickable.
     */
    protected function processUrls(string $content): string
    {
        // Pattern for URLs
        $pattern = '/\b(?:https?:\/\/|www\.)[^\s<>"]+/i';
        
        $content = preg_replace_callback($pattern, function ($matches) {
            $url = $matches[0];
            
            // Add protocol if missing
            $href = Str::startsWith($url, 'http') ? $url : 'https://' . $url;
            
            // Truncate display text for long URLs
            $displayUrl = Str::limit($url, 50);
            
            return '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener noreferrer" class="url-link">' . htmlspecialchars($displayUrl) . '</a>';
        }, $content);
        
        return $content;
    }

    /**
     * Clean up extra whitespace.
     */
    protected function cleanWhitespace(string $content): string
    {
        // Remove excessive whitespace but preserve intentional formatting
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        return trim($content);
    }

    /**
     * Extract plain text from formatted content.
     */
    public function toPlainText(string $content): string
    {
        // Strip all HTML tags
        $text = strip_tags($content);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Extract mentions from content.
     */
    public function extractMentions(string $content): array
    {
        $pattern = '/@([a-zA-Z0-9_]+)/';
        preg_match_all($pattern, $content, $matches);
        
        return array_unique($matches[1]);
    }

    /**
     * Extract hashtags from content.
     */
    public function extractHashtags(string $content): array
    {
        $pattern = '/#([a-zA-Z0-9_]+)/';
        preg_match_all($pattern, $content, $matches);
        
        return array_unique($matches[1]);
    }

    /**
     * Extract URLs from content.
     */
    public function extractUrls(string $content): array
    {
        $pattern = '/\b(?:https?:\/\/|www\.)[^\s<>"]+/i';
        preg_match_all($pattern, $content, $matches);
        
        return array_unique($matches[0]);
    }

    /**
     * Get content preview for feeds.
     */
    public function getPreview(string $content, int $length = 150): string
    {
        $plainText = $this->toPlainText($content);
        return Str::limit($plainText, $length);
    }

    /**
     * Count words in content.
     */
    public function wordCount(string $content): int
    {
        $plainText = $this->toPlainText($content);
        return str_word_count($plainText);
    }

    /**
     * Validate content length and format.
     */
    public function validate(string $content, int $maxLength = 5000): array
    {
        $errors = [];
        
        $plainText = $this->toPlainText($content);
        $wordCount = $this->wordCount($content);
        
        if (strlen($plainText) > $maxLength) {
            $errors[] = "Content is too long. Maximum {$maxLength} characters allowed.";
        }
        
        if (empty(trim($plainText))) {
            $errors[] = "Content cannot be empty.";
        }
        
        // Check for spam patterns
        if ($this->detectSpam($content)) {
            $errors[] = "Content appears to be spam.";
        }
        
        return $errors;
    }

    /**
     * Simple spam detection.
     */
    protected function detectSpam(string $content): bool
    {
        $spamPatterns = [
            '/(.)\1{10,}/', // Repeated characters
            '/http[s]?:\/\/[^\s]{1,10}\.[a-z]{2,3}\/[^\s]{20,}/', // Suspicious long URLs
            '/\b(buy now|click here|free money|guaranteed|limited time)\b/i', // Spam keywords
        ];
        
        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
} 