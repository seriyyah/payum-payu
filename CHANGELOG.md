# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Payum gateway factory `payu` with Capture, Convert, Sync, Status, Notify, Refund and Cancel actions.
- PayU REST API 2.1 client over PSR-18 with OAuth `client_credentials` and `trusted_merchant` grants.
- Signed notification handling (MD5, SHA-1, SHA-256) with forward-only status transitions.
- Card-token recurring payments with automatic lookup of the buyer's stored card.
- `RetrievePayMethods` request to list a buyer's stored cards.
- Optional Symfony bundle registering the factory with PayumBundle and a redirect-free PSR-18 client.
