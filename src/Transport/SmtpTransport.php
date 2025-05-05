<?php

namespace ApeliMailers\Transport;

use ApeliMailers\Core\Message;
use ApeliMailers\Exception\MailException;

class SmtpTransport implements TransportInterface
{
    private string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private ?string $encryption;
    private $socket = null;

    public function __construct(
        string $host,
        int $port = 25,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }

    public function send(Message $message): bool
    {
        try {
            $this->connect();
            $this->authenticate();
            $this->sendEmail($message);
            $this->disconnect();
            return true;
        } catch (\RuntimeException $e) {
            $this->disconnect();
            throw new MailException("SMTP Error: " . $e->getMessage(), 0, $e);
        }
    }

    private function connect(): void
    {
        $context = stream_context_create();
        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \RuntimeException("Connection failed: $errstr ($errno)");
        }

        $response = fgets($this->socket);
        if (strpos($response, '220') === false) {
            throw new \RuntimeException("Server rejected connection: $response");
        }
    }

    private function authenticate(): void
    {
        if (!$this->username || !$this->password) {
            return;
        }

        $this->sendCommand("EHLO " . $this->host);
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
    }

    private function sendEmail(Message $message): void
    {
        $this->sendCommand("MAIL FROM:<{$message->getFrom()['email']}>");
        
        foreach ($message->getTo() as $recipient) {
            $this->sendCommand("RCPT TO:<{$recipient['email']}>");
        }

        $this->sendCommand("DATA");
        $this->sendCommand($this->buildEmailData($message));
        $this->sendCommand(".");
    }

    private function buildEmailData(Message $message): string
    {
        $headers = [
            "From: {$message->getFrom()['name']} <{$message->getFrom()['email']}>",
            "To: " . implode(", ", array_map(
                fn($to) => "{$to['name']} <{$to['email']}>",
                $message->getTo()
            )),
            "Subject: {$message->getSubject()}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=utf-8",
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $message->getHtmlBody();
    }

    private function sendCommand(string $command): string
    {
        fwrite($this->socket, $command . "\r\n");
        $response = fgets($this->socket);
        
        if (!str_starts_with($response, '2') && !str_starts_with($response, '3')) {
            throw new \RuntimeException("Command failed: $command → $response");
        }

        return $response;
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            try {
                $this->sendCommand("QUIT");
            } finally {
                fclose($this->socket);
                $this->socket = null;
            }
        }
    }
}