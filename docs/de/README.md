# Printable-SDK für PHP

Offizieller PHP-Client für die Integrations-API des Dokumentendienstes
[Printable](https://printable.dev-cloud.space): Vorlagen, Druckformulare,
Stapelverarbeitung, Dokumententwürfe und Webhook-Prüfung. Die HMAC-Signatur der
Anfragen (einfacher und strenger kanonischer Modus) ist eingebaut und
byteweise identisch mit der Server-Implementierung.

> 🌐 [English](../../README.md) · **Deutsch** · [Русский](../ru/README.md) · [中文](../zh/README.md)

Keine Laufzeitabhängigkeiten (`ext-curl`, `ext-json`). PHP 8.2+.

## Installation

```bash
composer require dskripchenko/printable-sdk
```

## Schnellstart

```php
use Dskripchenko\PrintableSdk\PrintableClient;

$client = new PrintableClient(
    baseUrl: 'https://printable.example.com',
    token: 'erp-main-7f3a',
    secretKey: getenv('PRINTABLE_SECRET'),
    salt: getenv('PRINTABLE_SALT'),
    strict: false, // true, wenn für den Zugang „gesamten Payload-Hash prüfen“ aktiv ist
);

// Ein Dokument rendern
$doc = $client->printFormCreate([
    'template' => 'invoice',
    'variables' => ['company' => ['name' => 'ACME Ltd']],
    'format' => 'link',
]);
echo $doc['link'];

// Stapel mit Archiv und E-Mail-Versand
$batch = $client->printFormBatch([
    'template' => 'certificate',
    'items' => [
        ['variables' => ['name' => 'Alice']],
        ['variables' => ['name' => 'Bob']],
    ],
    'archive' => true,
    'deliver' => ['email' => 'hr@example.com'],
]);
$status = $client->printFormBatchGet($batch['uuid']);
```

## API-Oberfläche

| Methode | Endpunkt |
| --- | --- |
| `templateList()` | `GET template/list` |
| `templateContract($slug, $version?)` | `GET template/contract` |
| `templateExport($slug, $version?)` | `GET template/export` |
| `templateImport($packageB64, $groupId, $options?)` | `POST template/import` |
| `printFormCreate($params)` | `POST print-form/create` |
| `printFormGet($uuid, $format?)` | `GET print-form/get` |
| `printFormRules($slug, $version?)` | `GET print-form/rules` |
| `printFormBatch($params)` | `POST print-form/batch` |
| `printFormBatchGet($uuid)` | `GET print-form/batch-get` |
| `documentDraftCreate($params)` | `POST document-draft/create` |
| `documentDraftGet($uuid)` | `GET document-draft/get` |
| `call($method, $endpoint, $params)` | alles Weitere |


Jede Methode gibt das dekodierte `payload`-Array aus dem Antwort-Umschlag zurück.
Fehler (nicht-2xx oder `success: false`) lösen `PrintableException` aus — mit
`status`, `errorKey` und dem rohen Fehler-`payload`.

## Webhooks

```php
$valid = $client->verifyWebhook(
    rawBody: $request->getContent(),
    signatureHeader: $request->header('X-Printable-Signature'),
    timestampHeader: $request->header('X-Printable-Timestamp'),
);
```

## Integration testen

Übergeben Sie ein eigenes `transport`-Callable, um HTTP ohne cURL zu stubben:

```php
$client = new PrintableClient(..., transport: function ($method, $url, $body) {
    return [200, '{"success":true,"payload":{"ok":true}}'];
});
```

## Lizenz

MIT
