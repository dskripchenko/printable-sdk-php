# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries for releases published before this file existed were reconstructed from
the tagged commit history.

## [Unreleased]

## [1.1.0] - 2026-10-22

### Added
- `templateImportDocx()` — a Word document as a template draft.
- `printFormSignatureRequest()` and `printFormSignatureComplete()` — signing a
  document by an external signer (a qualified signature in the signer's own
  CryptoPro, a token): ask for the digest with the signer's certificate, hand
  in the detached CMS.
- `templateImportXlsx()` — an Excel workbook as a template draft; the group by
  id or by path (created when missing).
- `printFormSignatureInvite()` — inviting the document's recipient to sign it via a personal
  link to a signing page (CryptoPro plug-in, a detached .sig upload, or a
  PDF signed in its field); with an email the service also sends the link.
- Docs: the `preset`, `signing` (profiles, recipients) and `protection`
  parameters of `print-form/create` / `print-form/batch`, the `protection`
  block of the response (passwords are returned only once), `method` and
  `signature_link` on signature slots.

## [1.0.0] - 2026-07-27

### Added
- First release: PHP client for the Printable integration API, covering templates,
  print forms, batches, drafts and webhooks, with HMAC request signing.
