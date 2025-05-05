<?php

namespace ApeliMailers\Transport;

use ApeliMailers\Core\Message;

interface TransportInterface
{
    public function send(Message $message): bool;
}