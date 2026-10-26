<?php

declare(strict_types=1);

namespace Zana\Translation;

/**
 * One value per locale, such as a post's title (FE-30):
 *
 *     $title = new LocalizedValue(['en' => 'Launch', 'fr' => 'Lancement']);
 *     $title->in('fr');                        // "Lancement"
 *     $title->in('sw');                        // null: not translated
 *     $title->in('sw', fallback: 'en');        // "Launch"
 *     $title->missing(['en', 'fr', 'sw']);     // ['sw']: show it to editors
 *
 * Content is published per locale: lists should skip items not translated in
 * the current locale rather than fall back silently.
 */
final readonly class LocalizedValue implements \JsonSerializable
{
    /** @var array<string, string> */
    private array $values;

    /**
     * @param array<string, string|null> $values locale => value; blank values count as missing
     */
    public function __construct(array $values)
    {
        $this->values = array_filter($values, static fn (?string $value): bool => $value !== null && trim($value) !== '');
    }

    public function in(string $locale, ?string $fallback = null): ?string
    {
        return $this->values[$locale] ?? ($fallback === null ? null : $this->values[$fallback] ?? null);
    }

    public function has(string $locale): bool
    {
        return isset($this->values[$locale]);
    }

    /**
     * @param list<string> $locales
     * @return list<string> the locales without a value
     */
    public function missing(array $locales): array
    {
        return array_values(array_filter($locales, fn (string $locale): bool => !$this->has($locale)));
    }

    public function with(string $locale, string $value): self
    {
        return new self([...$this->values, $locale => $value]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
