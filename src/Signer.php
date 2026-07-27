<?php

declare(strict_types=1);

namespace Dskripchenko\PrintableSdk;

/**
 * Request/webhook signing, byte-compatible with the Printable server
 * (`App\Auth\HashSigner`).
 *
 *  - simple: sha256(token + timestamp + salt + secret_key)
 *  - strict: HMAC-SHA256(canonical(payload without token/hash), secret + salt)
 *    where canonical = recursive key sort + JSON with unescaped unicode/slashes
 *    and PHP's JSON_NUMERIC_CHECK semantics (numeric strings become numbers).
 *  - webhook: HMAC-SHA256("{timestamp}.{rawBody}", secret)
 */
final class Signer
{
    public function __construct(private readonly string $salt)
    {
        if ($this->salt === '') {
            throw new \InvalidArgumentException('Printable salt must not be empty.');
        }
    }

    public function signToken(string $token, int $timestamp, string $secretKey): string
    {
        return hash('sha256', "{$token}{$timestamp}{$this->salt}{$secretKey}");
    }

    /**
     * @param array<string, mixed> $payload request body WITHOUT token/hash
     */
    public function signPayload(array $payload, string $secretKey): string
    {
        return hash_hmac('sha256', $this->canonicalize($payload), $secretKey . $this->salt);
    }

    /**
     * Verify an incoming Printable webhook (X-Printable-Signature header,
     * value without the "sha256=" prefix).
     */
    public function verifyWebhook(string $signature, int $timestamp, string $rawBody, string $secretKey): bool
    {
        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secretKey);

        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function canonicalize(array $payload): string
    {
        return (string) json_encode(
            self::sortDeep($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK,
        );
    }

    /**
     * @param  array<string|int, mixed> $value
     * @return array<string|int, mixed>
     */
    private static function sortDeep(array $value): array
    {
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = self::sortDeep($v);
            } elseif (is_object($v)) {
                $value[$k] = self::sortDeep((array) $v);
            }
        }

        return $value;
    }
}
