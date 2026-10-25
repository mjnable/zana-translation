<?php

declare(strict_types=1);

namespace Zana\Translation;

/**
 * Translated messages by locale. A catalog file is <locale>.php returning a
 * nested array; keys are flattened with dots (['nav' => ['about' => 'About']]
 * gives "nav.about"). Catalogs added later override earlier keys.
 */
final class Catalogs
{
    /** @var array<string, array<string, string>> locale => key => message */
    private array $messages = [];

    /**
     * Add every <locale>.php file of a directory. A missing directory adds nothing.
     *
     * @throws TranslationException When a file does not return an array
     */
    public function addDirectory(string $directory): self
    {
        foreach (glob(rtrim($directory, '/\\') . '/*.php') ?: [] as $file) {
            $messages = (static fn (string $path): mixed => require $path)($file);
            if (!is_array($messages)) {
                throw new TranslationException("The catalog {$file} must return an array, got " . get_debug_type($messages) . '.');
            }
            $this->add(basename($file, '.php'), $messages);
        }

        return $this;
    }

    /**
     * @param array<array-key, mixed> $messages
     */
    public function add(string $locale, array $messages): self
    {
        $this->messages[strtolower($locale)] = [...($this->messages[strtolower($locale)] ?? []), ...self::flatten($messages)];

        return $this;
    }

    public function get(string $locale, string $key): ?string
    {
        return $this->messages[strtolower($locale)][$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(string $locale): array
    {
        return $this->messages[strtolower($locale)] ?? [];
    }

    /**
     * @param array<array-key, mixed> $messages
     * @return array<string, string>
     */
    private static function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $value) {
            $path = $prefix . $key;
            if (is_array($value)) {
                $flat = [...$flat, ...self::flatten($value, $path . '.')];
            } elseif (is_scalar($value) || $value instanceof \Stringable) {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }
}
