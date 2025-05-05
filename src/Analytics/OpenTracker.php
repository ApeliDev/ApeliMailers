<?php

namespace ApeliMailers\Analytics\Trackers;

use ApeliMailers\Core\Message;

class OpenTracker
{
    private string $trackingUrl;
    private Message $message;

    /**
     * Create a new open tracker
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
     * Inject open tracking pixel into HTML content
     *
     * @param string $html HTML content
     * @return string Modified HTML with tracking pixel
     */
    public function inject(string $html): string
    {
        // Create message identifier based on available data
        // Using hash of recipient + subject as a unique message identifier
        $recipientHash = md5(json_encode($this->message->getTo()) . $this->message->getSubject());
        
        // Generate a unique open ID
        $openId = bin2hex(random_bytes(8));
        
        // Create tracking pixel URL
        $trackingUrl = sprintf(
            '%s/open/%s/%s',
            $this->trackingUrl,
            $recipientHash,
            $openId
        );
        
        // Create the invisible tracking pixel
        $trackingPixel = sprintf(
            '<img src="%s" alt="" width="1" height="1" border="0" style="height:1px !important; width:1px !important; border-width:0 !important; margin:0 !important; padding:0 !important; display:block !important; position:absolute !important; top:0 !important; left:-9999px !important;">',
            htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8')
        );
        
        // If HTML is empty, create a minimal HTML structure
        if (empty(trim($html))) {
            return '<html><body>' . $trackingPixel . '</body></html>';
        }
        
        // Check if there's a </body> tag to insert before
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $trackingPixel . '</body>', $html, 1);
        }
        
        // Otherwise append to the end
        return $html . $trackingPixel;
    }
}