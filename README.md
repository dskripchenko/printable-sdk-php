# Printable SDK for PHP

Official PHP client for the [Printable](https://printable.dev-cloud.space) document-generation
service integration API: templates, print-forms, batch rendering, document
drafts and webhook verification — with the HMAC request signing
(simple and strict canonical modes) built in and byte-compatible with the
server implementation.

> 🌐 **English** · [Deutsch](docs/de/README.md) · [Русский](docs/ru/README.md) · [中文](docs/zh/README.md)

Zero runtime dependencies (`ext-curl`, `ext-json`). PHP 8.2+.

## Install

```bash
composer require dskripchenko/printable-sdk
```

## Quick start

```php
use Dskripchenko\PrintableSdk\PrintableClient;

$client = new PrintableClient(
    baseUrl: 'https://printable.example.com',
    token: 'erp-main-7f3a',
    secretKey: getenv('PRINTABLE_SECRET'),
    salt: getenv('PRINTABLE_SALT'),
    strict: false, // true if the credential has "verify full payload hash" enabled
);

// Render a document
$doc = $client->printFormCreate([
    'template' => 'invoice',
    'variables' => ['company' => ['name' => 'ACME Ltd']],
    'format' => 'link',
]);
echo $doc['link'];

// Batch with archive + email delivery
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

## API surface

| Method | Endpoint |
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
| `call($method, $endpoint, $params)` | anything else |

Every method returns the decoded `payload` array of the response envelope.
Errors (non-2xx or `success: false`) raise `PrintableException` with
`status`, `errorKey` and the raw error `payload`.

## Signing and protection

`print-form/create` and `print-form/batch` accept optional parameters, passed as-is in the params:

- `preset` — a print-preset code used instead of `template` (`document-draft/create` accepts it too);
- `signing` — a signature profile per slot, a visible or invisible signature, the reason, and `recipients` — the counterparties who sign the document themselves;
- `protection` — PDF protection: `{"generate": true}` lets the service generate the password, or pass your own one under `password`; the optional `permissions` is `read_print`, `read_only` or `forms_sign`.

The response then carries a `protection` block (`protected`, `permissions`, `password`, `owner_password`). **Passwords are returned only once** — in the response that issues the document; afterwards they come back masked (`••••••••`), so store them right away. Each `signature.slots[]` item may carry `method` (`cms`, `plugin`, `pdf` or `sig`) and `signature_link` — the recipient's detached `.sig`.

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
// The password comes only in this response — store it now
$protection = $doc['protection'];

// Hand the link over yourself, or let the service email it
$invite = $client->printFormSignatureInvite($doc['uuid'], 'buyer@example.com', 'buyer', 'Buyer LLC');
echo $invite['sign_url'];
```

`printFormSignatureInvite()` invites the recipient to sign: it returns `sign_url` — a personal link to the signing page (CryptoPro browser plug-in, a detached `.sig` upload, or a PDF signed in its field) — and `expires_at`. With an email address the service also sends the link (`sent`).

## Webhooks

```php
$valid = $client->verifyWebhook(
    rawBody: $request->getContent(),
    signatureHeader: $request->header('X-Printable-Signature'),
    timestampHeader: $request->header('X-Printable-Timestamp'),
);
```

## Testing your integration

Pass a custom `transport` callable to stub HTTP without touching cURL:

```php
$client = new PrintableClient(..., transport: function ($method, $url, $body) {
    return [200, '{"success":true,"payload":{"ok":true}}'];
});
```

## License

MIT
