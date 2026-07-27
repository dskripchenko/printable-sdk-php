<?php

declare(strict_types=1);

use Dskripchenko\PrintableSdk\Signer;

/**
 * Vectors generated from the Printable server implementation
 * (App\Auth\HashSigner, salt-123 / sec-1) — byte-compatibility contract.
 */
const VECTOR_SALT = 'salt-123';
const VECTOR_SECRET = 'sec-1';

it('signToken matches the server simple-mode formula', function () {
    $signer = new Signer(VECTOR_SALT);
    expect($signer->signToken('tok-1', 1753600000, VECTOR_SECRET))
        ->toBe('bf9a3d9e15dbac35b13b0324bac385564bcec7a1d9f4fc56c7da5293da3ebf02');
});

it('signPayload matches server strict-mode vectors', function (array $payload, string $canonical, string $hash) {
    $signer = new Signer(VECTOR_SALT);
    // Payload как его видит сервер: после json-decode.
    $decoded = json_decode((string) json_encode($payload), true);
    expect($signer->canonicalize($decoded))->toBe($canonical);
    expect($signer->signPayload($decoded, VECTOR_SECRET))->toBe($hash);
})->with([
    'sorted keys + list preserved' => [
        ['b' => 1, 'a' => 'x', 'nested' => ['z' => true, 'a' => [3, 1, 2]]],
        '{"a":"x","b":1,"nested":{"a":[3,1,2],"z":true}}',
        'f14fc443af8b3891f927aeb32c725923a4dc42fbf78eba2ce1439940be4ec5c2',
    ],
    'unicode + numeric string' => [
        ['template' => 'invoice', 'timestamp' => 1753600000, 'variables' => ['user' => ['name' => 'Иван/Ко', 'age' => '42']]],
        '{"template":"invoice","timestamp":1753600000,"variables":{"user":{"age":42,"name":"Иван/Ко"}}}',
        '3ede97642552144bbb774d077f56bc9c5fc9fe16e8d529a1941d7d155dd3faa6',
    ],
    'numeric-check edge cases' => [
        ['num_str' => '100.0', 'exp' => '1e3', 'zeros' => '007', 'plain' => 'text', 'empty_obj' => new stdClass, 'empty_arr' => []],
        '{"empty_arr":[],"empty_obj":[],"exp":1000,"num_str":100,"plain":"text","zeros":7}',
        '35698b058d732971e4f74a3ef78db7ea1662b5758e03f353500dc2f9f7448be4',
    ],
    'nested objects in list + null + false' => [
        ['list' => ['b' => 2, 'items' => [['k' => 'v', 'a' => 1], ['x' => null]]], 'flag' => false],
        '{"flag":false,"list":{"b":2,"items":[{"a":1,"k":"v"},{"x":null}]}}',
        'be151b065cdd8f2eb10d79a463ddc7467a185d154255fb427335574b8acbc845',
    ],
]);

it('verifyWebhook matches the server webhook signature', function () {
    $signer = new Signer(VECTOR_SALT);
    expect($signer->verifyWebhook(
        '7641aadedab7b606585ba0c68ddfe4583b778b7a9bef62c700c76f9596dc9141',
        1753600000,
        '{"uuid":"abc"}',
        VECTOR_SECRET,
    ))->toBeTrue();
    expect($signer->verifyWebhook('wrong', 1753600000, '{"uuid":"abc"}', VECTOR_SECRET))->toBeFalse();
});

it('rejects an empty salt', function () {
    expect(fn () => new Signer(''))->toThrow(InvalidArgumentException::class);
});
