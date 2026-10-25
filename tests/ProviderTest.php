<?php

declare(strict_types=1);

namespace Zana\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\Module;
use Zana\Translation\LocaleContext;
use Zana\Translation\TranslationServiceProvider;
use Zana\Translation\TranslatorInterface;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;
use Zana\Validation\ValidationServiceProvider;
use Zana\Validation\Validator;

final class ProviderTest extends TestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = Application::configure(__DIR__ . '/app')
            ->withEnvironment('testing')
            ->withConfiguration(['translation.locales' => ['en', 'fr']])
            ->withProviders(TranslationServiceProvider::class, ValidationServiceProvider::class)
            ->withModules(ContactModule::class)
            ->withServices(static fn (ServiceCollectionInterface $s) => $s)
            ->build()
            ->container();
    }

    public function testModuleCatalogsLoadBeforeTheApplicationOnes(): void
    {
        $translator = $this->container->get(TranslatorInterface::class);
        assert($translator instanceof TranslatorInterface);

        $this->assertSame('Envoyer', $translator->translate('contact.send', locale: 'fr'));
        $this->assertSame('Écrivez-nous', $translator->translate('contact.title', locale: 'fr'));
        $this->assertSame('Write to us', $translator->translate('contact.title'));
    }

    public function testValidationMessagesAreTranslatedInTheCurrentLocale(): void
    {
        $validator = $this->container->get(Validator::class);
        $context = $this->container->get(LocaleContext::class);
        assert($validator instanceof Validator && $context instanceof LocaleContext);

        $messages = $context->run('fr', static fn (): array => $validator->validate(new ContactMessage('', 'Hi'))->toArray());

        $this->assertSame([
            'name' => ['Le nom est obligatoire.'],
            'message' => ['Au moins 10 caractères.'],
        ], $messages);
    }
}

final class ContactModule extends Module
{
    public function name(): string
    {
        return 'Contact';
    }

    public function path(string ...$segments): string
    {
        return implode('/', [__DIR__ . '/app/modules/Contact', ...$segments]);
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}

final readonly class ContactMessage
{
    public function __construct(
        #[NotBlank(message: 'contact.error.name_required')]
        public string $name,
        #[Length(min: 10)]
        public string $message,
    ) {
    }
}
