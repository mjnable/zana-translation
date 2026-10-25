<?php

declare(strict_types=1);

namespace Zana\Translation;

/**
 * The current locale. LocaleMiddleware sets it for the duration of a
 * request and restores the previous one after; outside a request it is the
 * fallback. It is a singleton, so singletons such as the validator and the
 * view renderer can read it.
 */
final class LocaleContext
{
    /** @var list<string> */
    private array $stack = [];

    public function __construct(private readonly Locales $locales)
    {
    }

    public function get(): string
    {
        return $this->stack === [] ? $this->locales->fallback() : $this->stack[array_key_last($this->stack)];
    }

    /**
     * Change the current locale, e.g. in a console command.
     *
     * @throws TranslationException For an unsupported locale
     */
    public function set(string $locale): void
    {
        $locale = $this->supported($locale);
        array_pop($this->stack);
        $this->stack[] = $locale;
    }

    /**
     * Run a callback with the locale, then restore the previous one, also
     * when the callback throws.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws TranslationException For an unsupported locale
     */
    public function run(string $locale, callable $callback): mixed
    {
        $this->stack[] = $this->supported($locale);
        try {
            return $callback();
        } finally {
            array_pop($this->stack);
        }
    }

    private function supported(string $locale): string
    {
        return $this->locales->find($locale) ?? throw new TranslationException("The locale \"{$locale}\" is not supported.");
    }
}
