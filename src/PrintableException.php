<?php

declare(strict_types=1);

namespace Dskripchenko\PrintableSdk;

/**
 * API-level error: non-2xx response or `success: false` envelope.
 */
final class PrintableException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $payload decoded error payload (errorKey, message, messages…)
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorKey = null,
        public readonly array $payload = [],
    ) {
        parent::__construct($message, $status);
    }
}
