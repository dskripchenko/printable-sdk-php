# Printable SDK for PHP

面向文档生成服务 [Printable](https://printable.dev-cloud.space) 集成 API 的官方 PHP 客户端：
模板、打印表单、批量渲染、文档草稿以及 webhook 校验。HMAC 请求签名
（简单模式与严格规范模式）已内置，并与服务端实现逐字节一致。

> 🌐 [English](../../README.md) · [Deutsch](../de/README.md) · [Русский](../ru/README.md) · **中文**

运行时零依赖（`ext-curl`、`ext-json`）。PHP 8.2+。

## 安装

```bash
composer require dskripchenko/printable-sdk
```

## 快速开始

```php
use Dskripchenko\PrintableSdk\PrintableClient;

$client = new PrintableClient(
    baseUrl: 'https://printable.example.com',
    token: 'erp-main-7f3a',
    secretKey: getenv('PRINTABLE_SECRET'),
    salt: getenv('PRINTABLE_SALT'),
    strict: false, // 若该凭据开启了“校验完整载荷哈希”，则填 true
);

// 渲染一份文档
$doc = $client->printFormCreate([
    'template' => 'invoice',
    'variables' => ['company' => ['name' => 'ACME Ltd']],
    'format' => 'link',
]);
echo $doc['link'];

// 批量生成，打包并邮件送达
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

## API 一览

| 方法 | 接口 |
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
| `call($method, $endpoint, $params)` | 其他任意调用 |


每个方法都返回响应信封中解码后的 `payload` 数组。
出错时（非 2xx 或 `success: false`）抛出 `PrintableException`，其中带有
`status`、`errorKey` 以及原始的错误 `payload`。

## Webhook

```php
$valid = $client->verifyWebhook(
    rawBody: $request->getContent(),
    signatureHeader: $request->header('X-Printable-Signature'),
    timestampHeader: $request->header('X-Printable-Timestamp'),
);
```

## 为集成打桩测试

传入自定义的 `transport` 可调用对象，即可在不碰 cURL 的情况下打桩 HTTP：

```php
$client = new PrintableClient(..., transport: function ($method, $url, $body) {
    return [200, '{"success":true,"payload":{"ok":true}}'];
});
```

## 许可证

MIT
