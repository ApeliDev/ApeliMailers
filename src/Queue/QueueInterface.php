<?php

namespace ApeliMailers\Queue;

use ApeliMailers\Core\Message;

interface QueueInterface
{
    /**
     * Add an email message to the queue
     *
     * @param Message $message The email message to queue
     * @param int $delay Seconds to delay sending (0 for immediate)
     * @return bool True on success, false on failure
     */
    public function push(Message $message, int $delay = 0): bool;

    /**
     * Process queued messages
     *
     * @param int $limit Maximum number of messages to process (0 for unlimited)
     * @return int Number of messages processed
     */
    public function process(int $limit = 0): int;

    /**
     * Get the number of pending messages in queue
     *
     * @return int
     */
    public function count(): int;

    /**
     * Clear the queue
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;

    /**
     * Retry failed messages
     *
     * @param int $maxAttempts Maximum retry attempts
     * @return int Number of messages retried
     */
    public function retryFailed(int $maxAttempts = 3): int;
}