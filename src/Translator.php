<?php

declare(strict_types=1);

namespace Zana\Translation;

/**
 * Looks messages up in the catalogs. Missing keys never throw: the fallback
 * locale is tried, then the key itself is returned, with its placeholders
 * replaced.
 */
final readonly class Translator implements TranslatorInterface
{
    private const string ICU_ARGUMENT = '/\{\s*\w+\s*,\s*(plural|select|selectordinal|number|date|time|spellout|ordinal|duration)\b/';

    public function __construct(
        private Catalogs $catalogs,
        private LocaleContext $context,
        private Locales $locales,
    ) {
    }

    public function translate(string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale ??= $this->context->get();
        $message = $this->catalogs->get($locale, $key)
            ?? $this->catalogs->get($this->locales->fallback(), $key)
            ?? $key;

        if ($parameters === []) {
            return $message;
        }

        if (class_exists(\MessageFormatter::class) && preg_match(self::ICU_ARGUMENT, $message) === 1) {
            $formatted = \MessageFormatter::formatMessage($locale, $message, array_map(
                static fn (mixed $value): mixed => $value instanceof \Stringable ? (string) $value : $value,
                $parameters
            ));
            if ($formatted !== false) {
                return $formatted;
            }
        }

        $replacements = [];
        foreach ($parameters as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($message, $replacements);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->catalogs->get($locale ?? $this->context->get(), $key) !== null;
    }
}
