<?php
namespace ApeliMailers\Webhook;
use ApeliMailers\Webhook\EventProcessor;

class WebhookHandler
{
    private EventProcessor $eventProcessor;
    
    public function __construct(EventProcessor $eventProcessor)
    {
        $this->eventProcessor = $eventProcessor;
    }
    
    /**
     * Handle the webhook request
     * 
     * @param string|array $payload The JSON payload as a string or already decoded array
     * @return void
     * @throws \InvalidArgumentException If the payload is invalid JSON
     */
    public function handle($payload): void
    {
        // If payload is a string, decode it as JSON
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON payload');
            }
        }
        
        // Ensure payload is an array after decoding or direct passing
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be a valid JSON string or array');
        }
        
        $this->eventProcessor->process($payload);
    }
    
    /**
     * Helper method to handle direct PHP input
     * 
     * @return void
     */
    public function handleRequest(): void
    {
        $input = file_get_contents('php://input');
        $this->handle($input);
    }
}