<?php

namespace ApeliMailers\Analytics;

use ApeliMailers\Core\Message;
use ApeliMailers\Analytics\Trackers\ClickTracker; 
use ApeliMailers\Analytics\Trackers\OpenTracker;

class Tracker
{
    private string $trackingUrl;
    private bool $trackOpens = true;
    private bool $trackClicks = true;

    public function __construct(string $trackingUrl)
    {
        $this->trackingUrl = rtrim($trackingUrl, '/');
    }

    public function injectTracking(Message $message): Message
    {
        $html = $message->getHtmlBody();

        if ($this->trackOpens) {
            $openTracker = new OpenTracker($this->trackingUrl, $message);
            $html = $openTracker->inject($html);
        }

        if ($this->trackClicks) {
            $clickTracker = new ClickTracker($this->trackingUrl, $message);
            $html = $clickTracker->inject($html);
        }

        return $message->html($html);
    }
}