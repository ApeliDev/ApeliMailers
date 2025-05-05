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
    private bool $authenticated = false;

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
            $this->sendEmail($message);
            return true;
        } catch (\Exception $e) {
            $this->logDebug("Error: " . $e->getMessage());
            throw new MailException("SMTP Error: " . $e->getMessage(), 0, $e);
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        // Choose the right protocol based on encryption setting
        $protocol = 'tcp';
        $context = stream_context_create();
        
        // If using SSL (implicit TLS), set the protocol
        if ($this->encryption === 'ssl') {
            $protocol = 'ssl';
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
        }

        $this->logDebug("Connecting to {$this->host}:{$this->port} using {$protocol}");
        
        // Connect to the server
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            "{$protocol}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \RuntimeException("Connection failed: $errstr ($errno)");
        }

        // Set stream timeout
        stream_set_timeout($this->socket, 30);
        
        // Read the server greeting
        $greeting = $this->getResponse();
        $this->logDebug("Server greeting: $greeting");
        
        if (strpos($greeting, '220') === false) {
            throw new \RuntimeException("Server rejected connection: $greeting");
        }

        // First, send EHLO
        $hostname = gethostname() ?: 'localhost';
        $ehloResponse = $this->sendCommand("EHLO {$hostname}");
        $this->logDebug("EHLO response: $ehloResponse");
        
        // If using TLS, initiate STARTTLS
        if ($this->encryption === 'tls') {
            $this->logDebug("Starting TLS negotiation");
            $tlsResponse = $this->sendCommand("STARTTLS");
            $this->logDebug("STARTTLS response: $tlsResponse");
            
            if (strpos($tlsResponse, '220') === false) {
                throw new \RuntimeException("Failed to start TLS: $tlsResponse");
            }
            
            // Enable TLS on the connection
            $crypto_methods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            
            // For PHP 5.6+
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            }
            
            $this->logDebug("Enabling TLS encryption");
            if (!@stream_socket_enable_crypto($this->socket, true, $crypto_methods)) {
                throw new \RuntimeException("Failed to enable TLS encryption");
            }
            
            $this->logDebug("TLS encryption enabled successfully");
            
            // After TLS is established, need to send EHLO again
            $ehloResponse = $this->sendCommand("EHLO {$hostname}");
            $this->logDebug("EHLO after TLS response: $ehloResponse");
        }
        
        // Authenticate if credentials are provided
        if ($this->username && $this->password) {
            $this->logDebug("Starting authentication");
            $authResponse = $this->sendCommand("AUTH LOGIN");
            $this->logDebug("AUTH LOGIN response: $authResponse");
            
            $userResponse = $this->sendCommand(base64_encode($this->username));
            $this->logDebug("Username response: $userResponse");
            
            $passResponse = $this->sendCommand(base64_encode($this->password));
            $this->logDebug("Password response: $passResponse");
            
            if (strpos($passResponse, '235') === false) {
                throw new \RuntimeException("Authentication failed: $passResponse");
            }
            
            $this->authenticated = true;
            $this->logDebug("Authentication successful");
        }
    }

    private function sendEmail(Message $message): void
    {
        // Make sure we are connected and authenticated
        if (!$this->socket || !$this->authenticated) {
            throw new \RuntimeException("Not connected or authenticated to SMTP server");
        }
        
        // Send MAIL FROM
        $fromResponse = $this->sendCommand("MAIL FROM:<{$message->getFrom()['email']}>");
        $this->logDebug("MAIL FROM response: $fromResponse");
        
        // Send RCPT TO for each recipient
        foreach ($message->getTo() as $recipient) {
            $rcptResponse = $this->sendCommand("RCPT TO:<{$recipient['email']}>");
            $this->logDebug("RCPT TO response: $rcptResponse");
        }
        
        // Send DATA command
        $dataResponse = $this->sendCommand("DATA");
        $this->logDebug("DATA response: $dataResponse");
        
        if (strpos($dataResponse, '354') === false) {
            throw new \RuntimeException("DATA command failed: $dataResponse");
        }
        
        // Send email content
        $emailData = $this->buildEmailData($message);
        fwrite($this->socket, $emailData . "\r\n.\r\n");
        
        // Get response after data
        $endDataResponse = $this->getResponse();
        $this->logDebug("End DATA response: $endDataResponse");
        
        if (strpos($endDataResponse, '250') === false) {
            throw new \RuntimeException("Failed to send email data: $endDataResponse");
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
            "Message-ID: <" . md5(uniqid(time())) . "@" . parse_url($this->host, PHP_URL_HOST) . ">",
            "X-Mailer: ApeliMailers",
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $message->getHtmlBody();
    }

    private function sendCommand(string $command): string
    {
        $this->logDebug("COMMAND: $command");
        
        if (!$this->socket || @feof($this->socket)) {
            throw new \RuntimeException("Connection closed");
        }
        
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
        if (!$this->socket || @feof($this->socket)) {
            throw new \RuntimeException("Connection closed while reading response");
        }
        
        $response = '';
        $startTime = time();
        $timeout = 30; // 30 seconds timeout
        
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
        if ($this->socket) {
            if (!@feof($this->socket)) {
                try {
                    $this->logDebug("Closing connection gracefully");
                    fwrite($this->socket, "QUIT\r\n");
                    
                    // Try to read the response but don't require it
                    stream_set_timeout($this->socket, 1);
                    $response = @fgets($this->socket);
                    if ($response) {
                        $this->logDebug("QUIT response: " . trim($response));
                    }
                } catch (\Exception $e) {
                    $this->logDebug("Error during disconnect: " . $e->getMessage());
                }
            }
            
            fclose($this->socket);
            $this->socket = null;
            $this->authenticated = false;
            $this->logDebug("Connection closed");
        }
    }
    
    private function logDebug(string $message): void
    {
        if ($this->debug) {
            error_log("[SMTP DEBUG] $message");
        }
    }
}