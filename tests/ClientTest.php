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

it('templateImportDocx отправляет документ и не шлёт пустое имя', function () {
    [$client, $captured] = makeClient();
    $client->templateImportDocx('UEsDBBQ=', 7);

    $request = $captured[0];
    expect($request['method'])->toBe('POST');
    expect($request['url'])->toBe('https://printable.test/api/integration/template/import-docx');
    expect($request['body']['docx_b64'])->toBe('UEsDBBQ=');
    expect($request['body']['group_id'])->toBe(7);
    // The name is optional: the service rejects an empty field on format
    // rather than accepting it as "a template with no title".
    expect($request['body'])->not->toHaveKey('name');
});

it('templateImportDocx передаёт имя, когда его задали', function () {
    [$client, $captured] = makeClient();
    $client->templateImportDocx('UEsDBBQ=', 7, 'Договор поставки');

    expect($captured[0]['body']['name'])->toBe('Договор поставки');
});

it('templateImportXlsx принимает id группы или её путь', function () {
    [$client, $captured] = makeClient();
    $client->templateImportXlsx('UEsDBBQ=', 7);
    $client->templateImportXlsx('UEsDBBQ=', 'Реестры / Отчёты', 'Отчёт');

    expect($captured[0]['url'])->toBe('https://printable.test/api/integration/template/import-xlsx');
    expect($captured[0]['body']['group_id'])->toBe(7);
    expect($captured[0]['body'])->not->toHaveKey('group');
    expect($captured[0]['body'])->not->toHaveKey('name');
    expect($captured[1]['body']['group'])->toBe('Реестры / Отчёты');
    expect($captured[1]['body'])->not->toHaveKey('group_id');
    expect($captured[1]['body']['name'])->toBe('Отчёт');
});

it('printFormSignatureRequest шлёт сертификат и поле подписи, когда оно задано', function () {
    [$client, $captured] = makeClient();
    $client->printFormSignatureRequest('11111111-1111-1111-1111-111111111111', 'MIIB');
    $client->printFormSignatureRequest('11111111-1111-1111-1111-111111111111', 'MIIB', 'counterparty');

    expect($captured[0]['method'])->toBe('POST');
    expect($captured[0]['url'])->toBe('https://printable.test/api/integration/print-form/signature-request');
    expect($captured[0]['body']['certificate'])->toBe('MIIB');
    expect($captured[0]['body'])->not->toHaveKey('slot');
    expect($captured[1]['body']['slot'])->toBe('counterparty');
});

it('printFormSignatureComplete шлёт CMS и формат ответа', function () {
    [$client, $captured] = makeClient();
    $client->printFormSignatureComplete('11111111-1111-1111-1111-111111111111', 'MIAG', format: 'b64');

    expect($captured[0]['url'])->toBe('https://printable.test/api/integration/print-form/signature-complete');
    expect($captured[0]['body']['cms'])->toBe('MIAG');
    expect($captured[0]['body']['format'])->toBe('b64');
    expect($captured[0]['body'])->not->toHaveKey('slot');
});

it('printFormSignatureInvite шлёт адрес и имя получателя, пропуская незаданные', function () {
    [$client, $captured] = makeClient();
    $client->printFormSignatureInvite('11111111-1111-1111-1111-111111111111');
    $client->printFormSignatureInvite('11111111-1111-1111-1111-111111111111', 'buyer@example.com', 'buyer', 'Buyer LLC');

    expect($captured[0]['method'])->toBe('POST');
    expect($captured[0]['url'])->toBe('https://printable.test/api/integration/print-form/signature-invite');
    expect($captured[0]['body']['uuid'])->toBe('11111111-1111-1111-1111-111111111111');
    expect($captured[0]['body'])->not->toHaveKey('email');
    expect($captured[0]['body'])->not->toHaveKey('slot');
    expect($captured[0]['body'])->not->toHaveKey('name');
    expect($captured[1]['body']['email'])->toBe('buyer@example.com');
    expect($captured[1]['body']['slot'])->toBe('buyer');
    expect($captured[1]['body']['name'])->toBe('Buyer LLC');
});
