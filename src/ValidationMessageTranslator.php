<?php

declare(strict_types=1);

namespace Zana\Translation;

use Zana\Validation\MessageTranslatorInterface;

/**
 * Translates validation messages in the current locale. Registered by
 * TranslationServiceProvider when mj-zana/validation is installed; custom
 * messages are then usually keys, e.g. #[NotBlank(message: 'contact.error.name_required')].
 */
final readonly class ValidationMessageTranslator implements MessageTranslatorInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function translate(string $template, array $parameters): string
    {
        return $this->translator->translate($template, $parameters);
    }
}
