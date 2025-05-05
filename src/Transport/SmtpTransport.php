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
    private bool $debug = false;

    public function __construct(
        string $host,
        int $port = 25,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null,
        bool $debug = false
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
        $this->debug = $debug;
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
        $protocol = 'tcp';
        $context = stream_context_create();
        
        // Use SSL if encryption is set to ssl
        if ($this->encryption === 'ssl') {
            $protocol = 'ssl';
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
        }

        $this->socket = stream_socket_client(
            "$protocol://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \RuntimeException("Connection failed: $errstr ($errno)");
        }

        $this->logDebug("Connected to server");
        $response = $this->getResponse();
        if (strpos($response, '220') === false) {
            throw new \RuntimeException("Server rejected connection: $response");
        }
    }

    private function authenticate(): void
    {
        // Use a fallback if gethostname() fails
        $hostname = gethostname();
        if ($hostname === false) {
            $hostname = 'localhost';
        }
        
        $this->sendCommand("EHLO " . $hostname);
        
        // Initiate TLS if requested
        if ($this->encryption === 'tls') {
            $this->logDebug("Starting TLS negotiation");
            $response = $this->sendCommand("STARTTLS");
            
            if (strpos($response, '220') === false) {
                throw new \RuntimeException("Failed to start TLS: $response");
            }
            
            // Enable crypto on the connection with proper method selection
            $crypto_methods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            
            // PHP 5.6+ compatibility
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            }
            
            $this->logDebug("Enabling TLS encryption");
            if (!stream_socket_enable_crypto(
                $this->socket,
                true,
                $crypto_methods
            )) {
                throw new \RuntimeException("Failed to enable TLS encryption");
            }
            $this->logDebug("TLS encryption enabled successfully");
            
            // Need to send EHLO again after TLS is established
            $this->sendCommand("EHLO " . $hostname);
        }
        
        // Only proceed with authentication if credentials are provided
        if (!$this->username || !$this->password) {
            $this->logDebug("No credentials provided, skipping authentication");
            return;
        }

        $this->logDebug("Starting authentication");
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
        $this->logDebug("Authentication successful");
    }

    private function sendEmail(Message $message): void
    {
        $this->sendCommand("MAIL FROM:<{$message->getFrom()['email']}>");
        
        foreach ($message->getTo() as $recipient) {
            $this->sendCommand("RCPT TO:<{$recipient['email']}>");
        }

        $this->sendCommand("DATA");
        
        // For DATA command, we don't use sendCommand as it has different response codes
        fwrite($this->socket, $this->buildEmailData($message) . "\r\n.\r\n");
        $response = $this->getResponse();
        $this->logDebug("RESPONSE: $response");
        
        if (strpos($response, '250') === false) {
            throw new \RuntimeException("Failed to send email data: $response");
        }
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
            "Date: " . date('r'),
            "Message-ID: <" . time() . rand(1000, 9999) . "@" . parse_url($this->host, PHP_URL_HOST) . ">",
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $message->getHtmlBody();
    }

    private function sendCommand(string $command): string
    {
        $this->logDebug("COMMAND: $command");
        fwrite($this->socket, $command . "\r\n");
        $response = $this->getResponse();
        $this->logDebug("RESPONSE: $response");
        
        $code = substr($response, 0, 3);
        if (!in_array($code[0], ['2', '3'])) {
            throw new \RuntimeException("Command failed: $command → $response");
        }

        return $response;
    }
    
    private function getResponse(): string
    {
        $response = '';
        $startTime = time();
        $timeout = 30; // 30 seconds timeout
        
        // Set socket timeout to prevent hang
        stream_set_timeout($this->socket, $timeout);
        
        while (($line = @fgets($this->socket)) !== false) {
            // Check for timeout
            if (time() - $startTime > $timeout) {
                throw new \RuntimeException("Timeout waiting for server response");
            }
            
            // Check for socket error
            $info = stream_get_meta_data($this->socket);
            if ($info['timed_out']) {
                throw new \RuntimeException("Socket timeout while reading response");
            }
            
            if ($line === false) {
                throw new \RuntimeException("Connection closed by remote server");
            }
            
            $response .= $line;
            // If the 4th character is a space, this is the last line of response
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        
        if (empty($response)) {
            throw new \RuntimeException("Empty response from server");
        }
        
        return $response;
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            try {
                // Only attempt QUIT if the socket is still valid
                if (@feof($this->socket) === false) {
                    $this->logDebug("Sending QUIT command");
                    // Write QUIT directly rather than using sendCommand to avoid exceptions
                    fwrite($this->socket, "QUIT\r\n");
                    // Wait briefly for a response but don't require one
                    stream_set_timeout($this->socket, 1);
                    $response = @fgets($this->socket);
                    if ($response) {
                        $this->logDebug("QUIT response: " . trim($response));
                    }
                }
            } catch (\Exception $e) {
                // Ignore exceptions during disconnect
                $this->logDebug("Error during disconnect: " . $e->getMessage());
            } finally {
                $this->logDebug("Closing socket connection");
                fclose($this->socket);
                $this->socket = null;
            }
        }
    }
    
    private function logDebug(string $message): void
    {
        if ($this->debug) {
            error_log("[SMTP DEBUG] $message");
        }
    }
}