<?php

declare(strict_types=1);

namespace Dskripchenko\PrintableSdk;

/**
 * Client for the Printable integration API (`/api/integration`).
 *
 * ```php
 * $client = new PrintableClient(
 *     baseUrl: 'https://printable.example.com',
 *     token: 'erp-main-…',
 *     secretKey: '…',
 *     salt: '…',
 *     strict: false, // true when the credential requires full-payload HMAC
 * );
 *
 * $doc = $client->printFormCreate(['template' => 'invoice', 'variables' => [...]]);
 * echo $doc['link'];
 * ```
 *
 * Every call returns the decoded `payload` array of the response envelope;
 * errors raise {@see PrintableException}.
 */
final class PrintableClient
{
    /** @var callable(string, string, array<string, mixed>): array{int, string} */
    private $transport;

    private readonly Signer $signer;

    /**
     * @param callable(string $method, string $url, array<string, mixed> $body): array{int, string}|null $transport
     *        custom transport returning [statusCode, rawBody]; defaults to cURL
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $secretKey,
        string $salt,
        private readonly bool $strict = false,
        private readonly int $timeout = 30,
        ?callable $transport = null,
    ) {
        $this->signer = new Signer($salt);
        $this->transport = $transport ?? $this->curlTransport(...);
    }

    // ── Templates ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function templateList(): array
    {
        return $this->call('GET', 'template/list');
    }

    /** @return array<string, mixed> */
    public function templateContract(string $template, ?string $version = null): array
    {
        return $this->call('GET', 'template/contract', array_filter([
            'template' => $template,
            'version' => $version,
        ], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function templateExport(string $template, ?string $version = null): array
    {
        return $this->call('GET', 'template/export', array_filter([
            'template' => $template,
            'version' => $version,
        ], static fn ($v) => $v !== null));
    }

    /**
     * @param  array{deps?: string, on_conflict?: string} $options
     * @return array<string, mixed>
     */
    public function templateImport(string $packageB64, int $groupId, array $options = []): array
    {
        return $this->call('POST', 'template/import', $options + [
            'package_b64' => $packageB64,
            'group_id' => $groupId,
        ]);
    }

    /**
     * Импорт Word-документа как черновика шаблона.
     *
     * Доступен credential с полным доступом и только там, где включён на
     * инсталляции: сервис отвечает `docx-import-disabled`, если выключен.
     *
     * @return array<string, mixed>
     */
    public function templateImportDocx(string $docxB64, int $groupId, ?string $name = null): array
    {
        return $this->call('POST', 'template/import-docx', array_filter([
            'docx_b64' => $docxB64,
            'group_id' => $groupId,
            'name' => $name,
        ], static fn ($v) => $v !== null));
    }

    // ── Print forms ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $params template, variables, format, sync, deliver, …
     * @return array<string, mixed>
     */
    public function printFormCreate(array $params): array
    {
        return $this->call('POST', 'print-form/create', $params);
    }

    /** @return array<string, mixed> */
    public function printFormGet(string $uuid, string $format = 'link'): array
    {
        return $this->call('GET', 'print-form/get', ['uuid' => $uuid, 'format' => $format]);
    }

    /** @return array<string, mixed> */
    public function printFormRules(string $template, ?string $version = null): array
    {
        return $this->call('GET', 'print-form/rules', array_filter([
            'template' => $template,
            'version' => $version,
        ], static fn ($v) => $v !== null));
    }

    /**
     * @param  array<string, mixed> $params template, items, archive, callback, deliver, …
     * @return array<string, mixed>
     */
    public function printFormBatch(array $params): array
    {
        return $this->call('POST', 'print-form/batch', $params);
    }

    /** @return array<string, mixed> */
    public function printFormBatchGet(string $uuid): array
    {
        return $this->call('GET', 'print-form/batch-get', ['uuid' => $uuid]);
    }

    // ── Document drafts ────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function documentDraftCreate(array $params): array
    {
        return $this->call('POST', 'document-draft/create', $params);
    }

    /** @return array<string, mixed> */
    public function documentDraftGet(string $uuid): array
    {
        return $this->call('GET', 'document-draft/get', ['uuid' => $uuid]);
    }

    // ── Webhooks ───────────────────────────────────────────────────────

    /**
     * Verify an incoming Printable webhook. Pass the raw request body and
     * the X-Printable-Signature / X-Printable-Timestamp header values.
     */
    public function verifyWebhook(string $rawBody, string $signatureHeader, string|int $timestampHeader): bool
    {
        $signature = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        return $this->signer->verifyWebhook($signature, (int) $timestampHeader, $rawBody, $this->secretKey);
    }

    // ── Core ───────────────────────────────────────────────────────────

    /**
     * Generic escape hatch: call any `{controller}/{action}` of the
     * integration API with signed auth parameters.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed> decoded `payload` of the envelope
     */
    public function call(string $method, string $endpoint, array $params = []): array
    {
        $timestamp = time();
        $payload = $params + ['timestamp' => $timestamp];

        $hash = $this->strict
            ? $this->signer->signPayload($payload, $this->secretKey)
            : $this->signer->signToken($this->token, $timestamp, $this->secretKey);

        $body = $payload + ['token' => $this->token, 'hash' => $hash];
        $url = rtrim($this->baseUrl, '/') . '/api/integration/' . ltrim($endpoint, '/');

        if (strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($body);
            $body = [];
        }

        [$status, $raw] = ($this->transport)(strtoupper($method), $url, $body);

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new PrintableException("Printable API returned a non-JSON response (HTTP {$status})", $status);
        }

        $payloadOut = (array) ($decoded['payload'] ?? []);
        if ($status >= 400 || ($decoded['success'] ?? false) !== true) {
            throw new PrintableException(
                (string) ($payloadOut['message'] ?? "Printable API error (HTTP {$status})"),
                $status,
                isset($payloadOut['errorKey']) ? (string) $payloadOut['errorKey'] : null,
                $payloadOut,
            );
        }

        return $payloadOut;
    }

    /**
     * @param  array<string, mixed> $body
     * @return array{int, string}
     */
    private function curlTransport(string $method, string $url, array $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new PrintableException('Unable to initialize cURL', 0);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new PrintableException("Printable API request failed: {$error}", 0);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, (string) $raw];
    }
}
