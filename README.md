# mj-zana/translation

Translation catalogs, locale negotiation and the request locale for Zana
applications.

```
composer require mj-zana/translation
```

## Setup

Register `TranslationServiceProvider` and list the locales; the first is the
fallback:

```php
// config/translation.php
return ['locales' => ['en', 'fr']];
```

Catalogs are PHP files named after their locale. Each module's
`Resources/translations/` folder is loaded in module order, then the
application's `translations/` folder, which wins:

```php
// translations/fr.php
return [
    'nav' => ['home' => 'Accueil'],
    'contact' => ['thanks' => 'Merci {name} !'],
];
```

```php
$translator->translate('nav.home');                         // current locale
$translator->translate('contact.thanks', ['name' => 'Ada'], 'fr');
```

## The request locale

Add `LocaleMiddleware` to `http.middleware`. It picks the locale from the
first path segment (`/fr/...`), then `Accept-Language`, then the fallback,
and sets:

- `LocaleContext` for the duration of the request;
- the `_locale` request attribute;
- `Content-Language` on the response, and `Vary: Accept-Language` when the
  browser's languages decided.

## Rules

- **Missing keys never throw.** The fallback locale is tried, then the key is
  returned with its placeholders replaced.
- **ICU messages** (`{count, plural, one {# item} other {# items}}`) are
  formatted when `ext-intl` is loaded.
- **Validation messages** are translated when `mj-zana/validation` is
  installed: use keys as custom messages,
  `#[NotBlank(message: 'contact.error.name_required')]`, and translate the
  built-in English messages by their text.
- **Outside a request** the locale is the fallback; `LocaleContext::run()`
  changes it for a block of code, e.g. in a console command.

## License

MIT
