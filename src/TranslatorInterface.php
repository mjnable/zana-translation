<?php

declare(strict_types=1);

namespace Zana\Translation;

interface TranslatorInterface
{
    /**
     * The message for a key in the locale (the current one by default),
     * falling back to the fallback locale, then to the key itself. {name}
     * placeholders are replaced; ICU messages are formatted when ext-intl is
     * loaded.
     *
     * @param array<string, scalar|\Stringable|null> $parameters
     */
    public function translate(string $key, array $parameters = [], ?string $locale = null): string;

    public function has(string $key, ?string $locale = null): bool;
}
