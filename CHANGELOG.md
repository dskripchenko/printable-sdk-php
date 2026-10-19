# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries for releases published before this file existed were reconstructed from
the tagged commit history.

## [Unreleased]

### Added
- `printFormSignatureRequest()` and `printFormSignatureComplete()` — signing a
  document by an external signer (a qualified signature in the signer's own
  CryptoPro, a token): ask for the digest with the signer's certificate, hand
  in the detached CMS.
- `templateImportXlsx()` — an Excel workbook as a template draft; the group by
  id or by path (created when missing).

## [1.0.0] - 2026-07-27

### Added
- First release: PHP client for the Printable integration API, covering templates,
  print forms, batches, drafts and webhooks, with HMAC request signing.
