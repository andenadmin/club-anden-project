<?php

namespace App\Services\WhatsAppFlows;

final class DecryptedFlowRequest
{
    public function __construct(
        public readonly array $payload,
        public readonly string $aesKey,
        public readonly string $iv,
    ) {}

    public function action(): string
    {
        return (string) ($this->payload['action'] ?? '');
    }
}
