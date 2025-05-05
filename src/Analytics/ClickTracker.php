<?php

namespace ApeliMailers\Analytics\Trackers;

use ApeliMailers\Core\Message;

class ClickTracker
{
    private string $trackingUrl;
    private Message $message;

    /**
     * Create a new click tracker
     *
     * @param string $trackingUrl Base URL for tracking
     * @param Message $message Message being tracked
     */
    public function __construct(string $trackingUrl, Message $message)
    {
        $this->trackingUrl = rtrim($trackingUrl, '/');
        $this->message = $message;
    }

    /**
     * Inject click tracking into HTML content
     *
     * @param string $html HTML content
     * @return string Modified HTML with tracking links
     */
    public function inject(string $html): string
    {
        // Use DOMDocument to parse HTML and modify links
        if (empty($html)) {
            return $html;
        }

        // Create a new DOM document
        $dom = new \DOMDocument();
        
        // Preserve UTF-8 encoding
        $previousValue = libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($previousValue);

        // Find all links
        $links = $dom->getElementsByTagName('a');
        $linksToReplace = [];

        // We need to collect links first because the NodeList is live and changes as we modify it
        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $originalUrl = $link->getAttribute('href');
                
                // Skip links that are already tracked or are anchors
                if ($this->shouldSkipLink($originalUrl)) {
                    continue;
                }
                
                $linksToReplace[] = [
                    'node' => $link,
                    'original_url' => $originalUrl
                ];
            }
        }

        // Now process the collected links
        foreach ($linksToReplace as $linkData) {
            $link = $linkData['node'];
            $originalUrl = $linkData['original_url'];
            
            // Create tracking URL
            $trackingUrl = $this->createTrackingUrl($originalUrl);
            
            // Replace the link
            $link->setAttribute('href', $trackingUrl);
        }

        // Get the modified HTML
        $modifiedHtml = $dom->saveHTML();
        
        return $modifiedHtml ?: $html; // Fallback to original if something goes wrong
    }

    /**
     * Check if a link should be skipped for tracking
     *
     * @param string $url URL to check
     * @return bool True if the link should be skipped
     */
    private function shouldSkipLink(string $url): bool
    {
        // Skip tracking for:
        // 1. Anchor links
        // 2. Links that are already tracked
        // 3. Links to unsubscribe, manage preferences, etc.
        
        // Skip anchor links
        if (strpos($url, '#') === 0) {
            return true;
        }
        
        // Skip already tracked links
        if (strpos($url, $this->trackingUrl) === 0) {
            return true;
        }
        
        // Skip common email utility links
        $skipKeywords = ['unsubscribe', 'manage-preferences', 'mailto:', 'tel:', 'sms:'];
        foreach ($skipKeywords as $keyword) {
            if (stripos($url, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Create a tracking URL for a link
     *
     * @param string $originalUrl Original URL to track
     * @return string Tracking URL
     */
    private function createTrackingUrl(string $originalUrl): string
    {
        // Generate a unique ID for this click
        $clickId = $this->generateClickId();
        
        
        // Using hash of recipient + subject as a unique message identifier
        $recipientHash = md5(json_encode($this->message->getTo()) . $this->message->getSubject());
        
        // Create tracking URL
        $trackingUrl = sprintf(
            '%s/click/%s/%s?url=%s',
            $this->trackingUrl,
            $recipientHash,
            $clickId,
            urlencode($originalUrl)
        );
        
        return $trackingUrl;
    }

    /**
     * Generate a unique click ID
     *
     * @return string Unique ID for this click
     */
    private function generateClickId(): string
    {
        return bin2hex(random_bytes(16));
    }
}