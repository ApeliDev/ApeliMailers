<?php

namespace ApeliMailers\Core;

use ApeliMailers\Template\TemplateEngineInterface;


class Message
{
    private string $subject = '';
    private string $htmlBody = '';
    private string $textBody = '';
    private array $from = [];
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private array $attachments = [];
    private array $headers = [];
    private ?TemplateEngineInterface $templateEngine;

    public function __construct(?TemplateEngineInterface $templateEngine = null)
    {
        $this->templateEngine = $templateEngine;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function text(string $text): self
    {
        $this->textBody = $text;
        return $this;
    }

    public function from(string $email, string $name = ''): self
    {
        $this->from = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function to(string $email, string $name = ''): self
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    public function template(string $templatePath, array $data = []): self
    {
        if (!$this->templateEngine) {
            throw new \Exception('Template engine not configured');
        }

        $this->htmlBody = $this->templateEngine->render($templatePath, $data);
        return $this;
    }

    public function attach(string $path, string $name = null, string $mimeType = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?? basename($path),
            'mimeType' => $mimeType
        ];
        return $this;
    }

    // Getters for all properties
    public function getSubject(): string { return $this->subject; }
    public function getHtmlBody(): string { return $this->htmlBody; }
    public function getTextBody(): string { return $this->textBody; }
    public function getFrom(): array { return $this->from; }
    public function getTo(): array { return $this->to; }
    public function getAttachments(): array { return $this->attachments; }
    // ... other getters
}