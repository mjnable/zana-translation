<?php

declare(strict_types=1);

namespace Zana\Translation\Tests;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Translation\Catalogs;
use Zana\Translation\LocaleContext;
use Zana\Translation\LocaleMiddleware;
use Zana\Translation\Locales;
use Zana\Translation\TranslationException;
use Zana\Translation\Translator;

final class TranslationTest extends TestCase
{
    // ── catalogs and the translator ────────────────────────────────────────

    public function testKeysAreFlattenedAndLaterCatalogsWin(): void
    {
        $catalogs = (new Catalogs())
            ->add('en', ['nav' => ['home' => 'Home', 'about' => 'About']])
            ->add('en', ['nav' => ['about' => 'About us']]);

        $this->assertSame('Home', $catalogs->get('en', 'nav.home'));
        $this->assertSame('About us', $catalogs->get('en', 'nav.about'));
        $this->assertNull($catalogs->get('fr', 'nav.home'));
    }

    public function testDirectoriesHoldOneFilePerLocale(): void
    {
        $catalogs = (new Catalogs())->addDirectory(__DIR__ . '/catalogs/module')->addDirectory(__DIR__ . '/catalogs/app')->addDirectory(__DIR__ . '/missing');

        $this->assertSame('Envoyer', $catalogs->get('fr', 'contact.send'));
        $this->assertSame('Écrivez-nous', $catalogs->get('fr', 'contact.title'), 'the application overrides the module');
    }

    public function testCatalogFilesMustReturnArrays(): void
    {
        $this->expectException(TranslationException::class);
        (new Catalogs())->addDirectory(__DIR__ . '/catalogs/broken');
    }

    public function testMessagesFallBackToTheFallbackLocaleThenTheKey(): void
    {
        [$translator, $context] = $this->translator();

        $this->assertSame('Bonjour Ada', $translator->translate('hello', ['name' => 'Ada'], 'fr'));
        $this->assertSame('Hello Ada', $translator->translate('hello', ['name' => 'Ada']), 'the current locale is the fallback');
        $this->assertSame('Only in English', $translator->translate('english.only', locale: 'fr'));
        $this->assertSame('missing.key {name}', $translator->translate('missing.key {name}'));
        $this->assertSame('Missing for Ada', $translator->translate('Missing for {name}', ['name' => 'Ada']));

        $context->run('fr', function () use ($translator): void {
            $this->assertSame('Bonjour Ada', $translator->translate('hello', ['name' => 'Ada']));
            $this->assertTrue($translator->has('hello'));
            $this->assertFalse($translator->has('english.only'));
        });
    }

    #[RequiresPhpExtension('intl')]
    public function testIcuMessagesAreFormattedWithIntl(): void
    {
        [$translator] = $this->translator();

        $this->assertSame('No messages', $translator->translate('inbox', ['count' => 0]));
        $this->assertSame('One message', $translator->translate('inbox', ['count' => 1]));
        $this->assertSame('3 messages', $translator->translate('inbox', ['count' => 3]));
    }

    // ── locales and negotiation ────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function headers(): iterable
    {
        yield 'empty' => ['', 'en'];
        yield 'exact' => ['fr', 'fr'];
        yield 'region falls back to language' => ['fr-BE,en;q=0.5', 'fr'];
        yield 'region supported' => ['fr-CA', 'fr-CA'];
        yield 'quality wins over order' => ['sw;q=0.9,fr;q=0.8,en;q=0.95', 'en'];
        yield 'order breaks ties' => ['fr,en', 'fr'];
        yield 'unsupported falls back' => ['de,es', 'en'];
        yield 'zero quality is refused' => ['fr;q=0,de', 'en'];
        yield 'wildcard' => ['de,*;q=0.5', 'en'];
        yield 'case-insensitive' => ['FR-ca', 'fr-CA'];
    }

    #[DataProvider('headers')]
    public function testNegotiation(string $header, string $expected): void
    {
        $this->assertSame($expected, (new Locales(['en', 'fr', 'fr-CA']))->negotiate($header));
    }

    public function testInvalidLocaleListsAreRejected(): void
    {
        $this->expectException(TranslationException::class);
        new Locales([]);
    }

    public function testTheContextRefusesUnsupportedLocalesAndRestoresAfterExceptions(): void
    {
        $context = new LocaleContext(new Locales(['en', 'fr']));

        try {
            $context->run('fr', static fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }
        $this->assertSame('en', $context->get());

        $context->set('fr');
        $this->assertSame('fr', $context->get());

        $this->expectException(TranslationException::class);
        $context->set('de');
    }

    // ── middleware ─────────────────────────────────────────────────────────

    public function testThePathPrefixWins(): void
    {
        [$response, $seen] = $this->through(new ServerRequest('GET', '/fr/a-propos', ['Accept-Language' => 'en']));

        $this->assertSame(['fr', 'fr'], $seen);
        $this->assertSame('fr', $response->getHeaderLine('Content-Language'));
        $this->assertFalse($response->hasHeader('Vary'), 'the URL decided, not the header');
    }

    public function testTheBrowserLanguageDecidesWithoutAPrefix(): void
    {
        [$response, $seen] = $this->through(new ServerRequest('GET', '/', ['Accept-Language' => 'fr-FR,fr;q=0.9']));

        $this->assertSame(['fr', 'fr'], $seen);
        $this->assertSame('Accept-Language', $response->getHeaderLine('Vary'));
    }

    public function testTheContextIsRestoredAfterTheRequest(): void
    {
        $context = new LocaleContext(new Locales(['en', 'fr']));
        $middleware = new LocaleMiddleware(new Locales(['en', 'fr']), $context);

        try {
            $middleware->process(new ServerRequest('GET', '/fr'), new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException('failed');
                }
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('en', $context->get());
    }

    public function testHandlerLanguageHeadersAreKept(): void
    {
        $locales = new Locales(['en', 'fr']);
        $response = (new LocaleMiddleware($locales, new LocaleContext($locales)))->process(new ServerRequest('GET', '/fr'), new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, ['Content-Language' => 'fr-CA']);
            }
        });

        $this->assertSame('fr-CA', $response->getHeaderLine('Content-Language'));
    }

    /**
     * @return array{ResponseInterface, list<mixed>} the response, and the request attribute and context locale the handler saw
     */
    private function through(ServerRequest $request): array
    {
        $locales = new Locales(['en', 'fr']);
        $context = new LocaleContext($locales);
        $handler = new class ($context) implements RequestHandlerInterface {
            /** @var list<mixed> */
            public array $seen = [];

            public function __construct(private readonly LocaleContext $context)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen = [$request->getAttribute('_locale'), $this->context->get()];

                return new Response();
            }
        };

        $response = (new LocaleMiddleware($locales, $context))->process($request, $handler);

        return [$response, $handler->seen];
    }

    /**
     * @return array{Translator, LocaleContext}
     */
    private function translator(): array
    {
        $locales = new Locales(['en', 'fr']);
        $context = new LocaleContext($locales);
        $catalogs = (new Catalogs())
            ->add('en', [
                'hello' => 'Hello {name}',
                'english' => ['only' => 'Only in English'],
                'inbox' => '{count, plural, =0 {No messages} one {One message} other {# messages}}',
            ])
            ->add('fr', ['hello' => 'Bonjour {name}']);

        return [new Translator($catalogs, $context, $locales), $context];
    }
}
