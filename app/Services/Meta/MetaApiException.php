<?php

namespace App\Services\Meta;

use RuntimeException;
use Throwable;

/**
 * Excepción tipada para errores de la WhatsApp Cloud API. El `kind` permite mapear a
 * `wa_status` distintos en `WhatsAppSender` (failed, failed_token, failed_blocked, etc.).
 */
class MetaApiException extends RuntimeException
{
    public const KIND_AUTH        = 'auth';
    public const KIND_BLOCKED     = 'blocked';
    public const KIND_RATE_LIMIT  = 'rate_limit';
    public const KIND_SERVER      = 'server';
    public const KIND_UNKNOWN     = 'unknown';

    public function __construct(
        string $message,
        public readonly string $kind,
        public readonly ?int $httpStatus = null,
        public readonly ?int $metaCode = null,
        public readonly ?array $rawResponse = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Mapeo del wa_status que se persiste en `conversation_messages.wa_status`.
     */
    public function asWaStatus(): string
    {
        return match ($this->kind) {
            self::KIND_AUTH       => 'failed_token',
            self::KIND_BLOCKED    => 'failed_blocked',
            self::KIND_RATE_LIMIT => 'failed_rate_limit',
            self::KIND_SERVER     => 'failed_server',
            default               => 'failed',
        };
    }
}
