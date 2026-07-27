<?php

declare(strict_types=1);

use Dskripchenko\PrintableSdk\PrintableClient;
use Dskripchenko\PrintableSdk\PrintableException;
use Dskripchenko\PrintableSdk\Signer;

/**
 * @return array{PrintableClient, array<int, array{method: string, url: string, body: array<string, mixed>}>&\ArrayAccess}
 */
function makeClient(bool $strict = false, int $status = 200, ?string $response = null): array
{
    $captured = new ArrayObject;
    $client = new PrintableClient(
        baseUrl: 'https://printable.test',
        token: 'tok-1',
        secretKey: 'sec-1',
        salt: 'salt-123',
        strict: $strict,
        transport: function (string $method, string $url, array $body) use ($captured, $status, $response): array {
            $captured[] = ['method' => $method, 'url' => $url, 'body' => $body];

            return [$status, $response ?? '{"success":true,"payload":{"ok":1}}'];
        },
    );

    return [$client, $captured];
}

it('POST call sends signed body with simple-mode hash', function () {
    [$client, $captured] = makeClient();
    $payload = $client->printFormCreate(['template' => 'invoice', 'variables' => ['a' => 1]]);

    expect($payload)->toBe(['ok' => 1]);
    $request = $captured[0];
    expect($request['method'])->toBe('POST');
    expect($request['url'])->toBe('https://printable.test/api/integration/print-form/create');
    $body = $request['body'];
    expect($body['token'])->toBe('tok-1');
    expect($body['template'])->toBe('invoice');
    expect($body['hash'])->toBe(
        (new Signer('salt-123'))->signToken('tok-1', (int) $body['timestamp'], 'sec-1'),
    );
});

it('strict mode signs the full payload without token/hash', function () {
    [$client, $captured] = makeClient(strict: true);
    $client->printFormCreate(['template' => 'invoice']);

    $body = $captured[0]['body'];
    $expectedPayload = $body;
    unset($expectedPayload['token'], $expectedPayload['hash']);
    expect($body['hash'])->toBe(
        (new Signer('salt-123'))->signPayload($expectedPayload, 'sec-1'),
    );
});

it('GET call puts auth into the query string', function () {
    [$client, $captured] = makeClient();
    $client->printFormGet('0f8fad5b-d9cb-469f-a165-70867728950e');

    $request = $captured[0];
    expect($request['method'])->toBe('GET');
    expect($request['body'])->toBe([]);
    parse_str((string) parse_url($request['url'], PHP_URL_QUERY), $query);
    expect($query['uuid'])->toBe('0f8fad5b-d9cb-469f-a165-70867728950e');
    expect($query['token'])->toBe('tok-1');
    expect($query)->toHaveKeys(['timestamp', 'hash']);
});

it('error envelope raises PrintableException with errorKey', function () {
    [$client] = makeClient(status: 401, response: '{"success":false,"payload":{"errorKey":"auth-token-invalid","message":"Bad token"}}');

    try {
        $client->templateList();
        $this->fail('Expected PrintableException');
    } catch (PrintableException $e) {
        expect($e->status)->toBe(401);
        expect($e->errorKey)->toBe('auth-token-invalid');
        expect($e->getMessage())->toBe('Bad token');
    }
});

it('non-JSON response raises PrintableException', function () {
    [$client] = makeClient(status: 502, response: '<html>Bad gateway</html>');
    expect(fn () => $client->templateList())->toThrow(PrintableException::class, 'non-JSON');
});

it('verifyWebhook accepts the sha256= prefixed header', function () {
    [$client] = makeClient();
    $signature = 'sha256='.hash_hmac('sha256', '1753600000.{"uuid":"abc"}', 'sec-1');
    expect($client->verifyWebhook('{"uuid":"abc"}', $signature, '1753600000'))->toBeTrue();
    expect($client->verifyWebhook('{"uuid":"abc"}', 'sha256=deadbeef', '1753600000'))->toBeFalse();
});
