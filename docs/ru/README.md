# Printable SDK для PHP

Официальный клиент на PHP к интеграционному API сервиса генерации документов
[Printable](https://printable.dev-cloud.space): шаблоны, печатные формы, пакетная
печать, черновики документов и проверка вебхуков. Подпись запросов HMAC
(простой и строгий канонический режимы) встроена и побайтово совпадает с
серверной реализацией.

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · **Русский** · [中文](../zh/README.md)

Никаких зависимостей в рантайме (`ext-curl`, `ext-json`). PHP 8.2+.

## Установка

```bash
composer require dskripchenko/printable-sdk
```

## Быстрый старт

```php
use Dskripchenko\PrintableSdk\PrintableClient;

$client = new PrintableClient(
    baseUrl: 'https://printable.example.com',
    token: 'erp-main-7f3a',
    secretKey: getenv('PRINTABLE_SECRET'),
    salt: getenv('PRINTABLE_SALT'),
    strict: false, // true, если у ключа включена «проверка хеша всего тела»
);

// Печать документа
$doc = $client->printFormCreate([
    'template' => 'invoice',
    'variables' => ['company' => ['name' => 'ACME Ltd']],
    'format' => 'link',
]);
echo $doc['link'];

// Пакет с архивом и отправкой на почту
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

## Поверхность API

| Метод | Адрес |
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
| `printFormBatch($params)` | `POST print-form/batch` |
| `printFormBatchGet($uuid)` | `GET print-form/batch-get` |
| `documentDraftCreate($params)` | `POST document-draft/create` |
| `documentDraftGet($uuid)` | `GET document-draft/get` |
| `call($method, $endpoint, $params)` | что угодно ещё |


Каждый метод возвращает раскодированный массив `payload` из конверта ответа.
Ошибки (не-2xx или `success: false`) бросают `PrintableException` с полями
`status`, `errorKey` и исходным `payload` ошибки.

## Вебхуки

```php
$valid = $client->verifyWebhook(
    rawBody: $request->getContent(),
    signatureHeader: $request->header('X-Printable-Signature'),
    timestampHeader: $request->header('X-Printable-Timestamp'),
);
```

## Проверка интеграции

Передайте свой `transport`-вызов, чтобы подменить HTTP, не трогая cURL:

```php
$client = new PrintableClient(..., transport: function ($method, $url, $body) {
    return [200, '{"success":true,"payload":{"ok":true}}'];
});
```

## Лицензия

MIT
