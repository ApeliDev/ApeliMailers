<?php

namespace ApeliMailers\Core;

use ApeliMailers\Transport\TransportInterface;
use ApeliMailers\Template\TemplateEngineInterface;
use ApeliMailers\Queue\QueueInterface; 
use ApeliMailers\Analytics\Tracker;
use Exception;

class Mailer
{
    private TransportInterface $transport;
    private ?TemplateEngineInterface $templateEngine = null;
    private ?QueueInterface $queue = null;
    private ?Tracker $tracker = null;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function setTemplateEngine(TemplateEngineInterface $templateEngine): self
    {
        $this->templateEngine = $templateEngine;
        return $this;
    }

    public function setQueue(QueueInterface $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function setTracker(Tracker $tracker): self
    {
        $this->tracker = $tracker;
        return $this;
    }

    public function createMessage(): Message
    {
        return new Message($this->templateEngine);
    }

    public function send(Message $message): bool
    {
        if ($this->queue) {
            return $this->queue->push($message);
        }

        if ($this->tracker) {
            $message = $this->tracker->injectTracking($message);
        }

        return $this->transport->send($message);
    }

    public function sendLater(Message $message, int $delay = 0): bool
    {
        if (!$this->queue) {
            throw new Exception('Queue system not configured');
        }

        return $this->queue->push($message, $delay);
    }
}