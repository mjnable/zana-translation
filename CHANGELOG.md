# Changelog

All Zana packages share one version number and are released together. A release that did
not change this package is not listed.

## 2.3.0 — 2026-10-26

### Changed

- Tests and tooling (`tests/`, `phpunit.xml.dist`, `phpstan.neon.dist`) are no longer in Composer
  archives, so they stay out of production `vendor/` folders (FE-17).

## 2.2.0 — 2026-10-26

### Added

- A remembered locale choice: `LocaleMiddleware` reads the locale cookie (`translation.cookie`)
  after the URL and before `Accept-Language`, and `LocaleSwitchAction` sets it (FE-26).
- `translation.redirect_root`: a request for `/` is redirected to `/<locale>`.
- `LocalizedValue`: one value per locale for content stored in the database (FE-30).

### Changed

- `LocaleMiddleware` adds `Vary: Cookie` when a locale cookie is configured. Requires
  `psr/http-factory`.

## 2.1.0 — 2026-10-26

First release. Translation catalogs per module and application, RFC 4647 locale negotiation,
the request locale (`LocaleContext`, `LocaleMiddleware`), ICU messages with `ext-intl`, and
translated validation messages.
