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
| `templateImportDocx($docxB64, $groupId, $name?)` | `POST template/import-docx` |
| `templateImportXlsx($xlsxB64, $groupIdOrPath, $name?)` | `POST template/import-xlsx` |
| `printFormCreate($params)` | `POST print-form/create` |
| `printFormGet($uuid, $format?)` | `GET print-form/get` |
| `printFormRules($slug, $version?)` | `GET print-form/rules` |
| `printFormSignatureRequest($uuid, $certificate, $slot?)` | `POST print-form/signature-request` |
| `printFormSignatureComplete($uuid, $cms, $slot?, $format?)` | `POST print-form/signature-complete` |
| `printFormSignatureInvite($uuid, $email?, $slot?, $name?)` | `POST print-form/signature-invite` |
| `printFormBatch($params)` | `POST print-form/batch` |
| `printFormBatchGet($uuid)` | `GET print-form/batch-get` |
| `documentDraftCreate($params)` | `POST document-draft/create` |
| `documentDraftGet($uuid)` | `GET document-draft/get` |
| `call($method, $endpoint, $params)` | alles Weitere |


Jede Methode gibt das dekodierte `payload`-Array aus dem Antwort-Umschlag zurück.
Fehler (nicht-2xx oder `success: false`) lösen `PrintableException` aus — mit
`status`, `errorKey` und dem rohen Fehler-`payload`.

## Signatur und Schutz

`print-form/create` und `print-form/batch` akzeptieren optionale Parameter, die unverändert in den params übergeben werden:

- `preset` — der Code einer Druckvorgabe anstelle von `template` (auch `document-draft/create` akzeptiert ihn);
- `signing` — ein Signaturprofil je Feld, eine sichtbare oder unsichtbare Signatur, der Grund und `recipients` — die Gegenparteien, die das Dokument selbst signieren;
- `protection` — PDF-Schutz: `{"generate": true}` lässt den Dienst das Passwort erzeugen, alternativ ein eigenes im Feld `password`; das optionale `permissions` ist `read_print`, `read_only` oder `forms_sign`.

Die Antwort enthält dann einen Block `protection` (`protected`, `permissions`, `password`, `owner_password`). **Passwörter werden nur einmal geliefert** — in der Antwort, die das Dokument ausstellt; danach kommen sie maskiert (`••••••••`), also sofort speichern. Jedes Element von `signature.slots[]` kann `method` (`cms`, `plugin`, `pdf` oder `sig`) und `signature_link` tragen — die abgetrennte `.sig` des Empfängers.

```php
$doc = $client->printFormCreate([
    'preset' => 'contract-signed',
    'variables' => ['contract' => ['number' => 15]],
    'signing' => [
        'slots' => ['company' => ['profile' => 'ceo']],
        'invisible' => false,
        'reason' => 'Contract 15',
        'recipients' => ['buyer' => ['email' => 'buyer@example.com', 'name' => 'Buyer LLC']],
    ],
    'protection' => ['generate' => true, 'permissions' => 'read_print'],
]);
// Das Passwort kommt nur in dieser Antwort — jetzt speichern
$protection = $doc['protection'];

// Den Link selbst weitergeben oder vom Dienst per E-Mail senden lassen
$invite = $client->printFormSignatureInvite($doc['uuid'], 'buyer@example.com', 'buyer', 'Buyer LLC');
echo $invite['sign_url'];
```

`printFormSignatureInvite()` lädt den Empfänger zur Signatur ein: Es liefert `sign_url` — einen persönlichen Link zur Signaturseite (CryptoPro-Browser-Plug-in, Upload einer abgetrennten `.sig` oder ein im eigenen Feld signiertes PDF) — und `expires_at`. Mit einer E-Mail-Adresse verschickt der Dienst den Link auch per E-Mail (`sent`).

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
