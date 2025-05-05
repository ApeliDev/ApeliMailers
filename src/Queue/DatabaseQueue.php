<?php

namespace ApeliMailers\Queue;

use ApeliMailers\Core\Message;
use PDO;

class DatabaseQueue implements QueueInterface
{
    private PDO $connection;
    private string $tableName;

    public function __construct(PDO $connection, string $tableName = 'email_queue')
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    public function push(Message $message, int $delay = 0): bool
    {
        $stmt = $this->connection->prepare("
            INSERT INTO {$this->tableName} 
            (message_data, send_at, attempts, created_at) 
            VALUES (?, ?, 0, NOW())
        ");

        $sendAt = $delay > 0 ? date('Y-m-d H:i:s', time() + $delay) : date('Y-m-d H:i:s');
        $messageData = serialize($message);

        return $stmt->execute([$messageData, $sendAt]);
    }

    public function process(int $limit = 0): int
    {
        $query = "SELECT * FROM {$this->tableName} 
                 WHERE send_at <= NOW() 
                 AND (attempts IS NULL OR attempts < 3)
                 ORDER BY created_at ASC";
        
        if ($limit > 0) {
            $query .= " LIMIT {$limit}";
        }

        $stmt = $this->connection->query($query);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $processed = 0;

        foreach ($messages as $queued) {
            // Process each message here
            $processed++;
        }

        return $processed;
    }

    public function count(): int
    {
        $stmt = $this->connection->query("SELECT COUNT(*) FROM {$this->tableName}");
        return (int) $stmt->fetchColumn();
    }

    public function clear(): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->tableName}");
        return $stmt->execute();
    }

    public function retryFailed(int $maxAttempts = 3): int
    {
        // Mark failed messages for retry
        $stmt = $this->connection->prepare("
            UPDATE {$this->tableName}
            SET attempts = 0
            WHERE attempts >= ?
            AND send_at <= NOW()
        ");
        
        $stmt->execute([$maxAttempts]);
        
        return $stmt->rowCount();
    }
}