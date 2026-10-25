<?php

declare(strict_types=1);

namespace Zana\Translation;

/**
 * The locales the application is published in, in order. The first one is
 * the fallback.
 */
final readonly class Locales
{
    /** @var non-empty-list<string> */
    public array $all;

    /**
     * @param list<string> $locales e.g. ['en', 'fr', 'fr-CA']
     */
    public function __construct(array $locales)
    {
        if ($locales === []) {
            throw new TranslationException('translation.locales must list at least one locale.');
        }
        foreach ($locales as $locale) {
            if (preg_match('/^[a-zA-Z]{2,8}(-[a-zA-Z0-9]{1,8})*$/', $locale) !== 1) {
                throw new TranslationException("\"{$locale}\" is not a valid locale tag.");
            }
        }
        $this->all = array_values(array_unique($locales));
    }

    public function fallback(): string
    {
        return $this->all[0];
    }

    public function supports(string $locale): bool
    {
        return $this->find($locale) !== null;
    }

    /**
     * The supported locale for a tag, matched case-insensitively, with the
     * supported spelling; null when unsupported.
     */
    public function find(string $locale): ?string
    {
        foreach ($this->all as $supported) {
            if (strcasecmp($supported, $locale) === 0) {
                return $supported;
            }
        }

        return null;
    }

    /**
     * The best supported locale for an Accept-Language header (RFC 4647
     * lookup): ranges by quality, then by order; each range is tried as is,
     * then without its last subtag ("fr-CA" then "fr"). Falls back to the
     * first locale.
     */
    public function negotiate(string $acceptLanguage): string
    {
        $ranges = [];
        foreach (explode(',', $acceptLanguage) as $position => $item) {
            $parts = explode(';', trim($item));
            $range = trim($parts[0]);
            $quality = 1.0;
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([01](?:\.\d{1,3})?)\s*$/i', $parameter, $match) === 1) {
                    $quality = (float) $match[1];
                }
            }
            if ($range !== '' && $quality > 0) {
                $ranges[] = [$quality, -$position, $range];
            }
        }
        rsort($ranges);

        foreach ($ranges as [, , $range]) {
            if ($range === '*') {
                return $this->fallback();
            }
            $tag = $range;
            while (true) {
                $found = $this->find($tag);
                if ($found !== null) {
                    return $found;
                }
                $dash = strrpos($tag, '-');
                if ($dash === false) {
                    break;
                }
                $tag = substr($tag, 0, $dash);
            }
        }

        return $this->fallback();
    }
}
