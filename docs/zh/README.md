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
| `call($method, $endpoint, $params)` | 其他任意调用 |


每个方法都返回响应信封中解码后的 `payload` 数组。
出错时（非 2xx 或 `success: false`）抛出 `PrintableException`，其中带有
`status`、`errorKey` 以及原始的错误 `payload`。

## 签名与保护

`print-form/create` 和 `print-form/batch` 接受以下可选参数，按原样放入 params 传递：

- `preset` —— 打印预设代码，用来代替 `template`（`document-draft/create` 也接受）；
- `signing` —— 每个签名栏的签名配置、可见或不可见签名、签署理由，以及 `recipients` —— 自行签署文档的对方；
- `protection` —— PDF 保护：`{"generate": true}` 由服务生成密码，或在 `password` 字段中给出自己的密码；可选的 `permissions` 为 `read_print`、`read_only` 或 `forms_sign`。

此时响应中带有 `protection` 块（`protected`、`permissions`、`password`、`owner_password`）。**密码只返回一次** —— 在签发文档的那次响应中；之后会被掩码（`••••••••`），请立即保存。`signature.slots[]` 的每一项可能带有 `method`（`cms`、`plugin`、`pdf` 或 `sig`）和 `signature_link` —— 接收方的分离式 `.sig`。

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
// 密码只在这次响应中返回 —— 请现在保存
$protection = $doc['protection'];

// 自行转交链接，或让服务通过邮件发送
$invite = $client->printFormSignatureInvite($doc['uuid'], 'buyer@example.com', 'buyer', 'Buyer LLC');
echo $invite['sign_url'];
```

`printFormSignatureInvite()` 邀请接收方签署：返回 `sign_url` —— 指向签名页面的个人链接（浏览器中的 CryptoPro 插件、上传分离式 `.sig`，或在其签名栏中签好的 PDF）—— 以及 `expires_at`。提供邮箱时，服务还会通过邮件发送该链接（`sent`）。

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
