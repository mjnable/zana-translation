<?php

declare(strict_types=1);

namespace Zana\Translation\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Translation\LocaleContext;
use Zana\Translation\LocaleMiddleware;
use Zana\Translation\Locales;
use Zana\Translation\LocaleSwitchAction;
use Zana\Translation\LocalizedValue;

final class RememberedLocaleTest extends TestCase
{
    public function testTheCookieWinsOverTheBrowserButNotOverTheUrl(): void
    {
        $middleware = $this->middleware();
        $browserEnglish = new ServerRequest('GET', '/contact', ['Accept-Language' => 'en']);

        $this->assertSame(['fr', true], $middleware->detect($browserEnglish->withCookieParams(['locale' => 'fr'])));
        $this->assertSame(['en', false], $middleware->detect((new ServerRequest('GET', '/en/about'))->withCookieParams(['locale' => 'fr'])));
        $this->assertSame(['en', true], $middleware->detect($browserEnglish->withCookieParams(['locale' => 'de'])), 'unsupported cookies are ignored');
    }

    public function testResponsesThatDependOnTheCookieVaryOnIt(): void
    {
        $response = $this->middleware()->process(new ServerRequest('GET', '/contact'), new Plain());

        $this->assertSame('Accept-Language, Cookie', $response->getHeaderLine('Vary'));
    }

    public function testTheRootRedirectsToTheVisitorsLanguage(): void
    {
        $response = $this->middleware()->process((new ServerRequest('GET', '/', ['Accept-Language' => 'en']))->withCookieParams(['locale' => 'fr']), new Plain());

        $this->assertSame([302, '/fr'], [$response->getStatusCode(), $response->getHeaderLine('Location')]);
        $this->assertSame('Accept-Language, Cookie', $response->getHeaderLine('Vary'));
    }

    public function testTheSwitcherRemembersTheChoiceAndRedirectsOnlyWithinTheSite(): void
    {
        $switch = new LocaleSwitchAction(new Locales(['en', 'fr']), new Psr17Factory());

        $response = $switch(new ServerRequest('GET', 'https://example.com/locale/fr?to=/fr/a-propos'), 'fr');
        $this->assertSame([303, '/fr/a-propos'], [$response->getStatusCode(), $response->getHeaderLine('Location')]);
        $this->assertSame('locale=fr; Path=/; Max-Age=31536000; SameSite=Lax; Secure', $response->getHeaderLine('Set-Cookie'));

        foreach (['//evil.example/x', 'https://evil.example', '/\\evil.example'] as $to) {
            $this->assertSame('/fr', $switch(new ServerRequest('GET', '/locale/fr?to=' . rawurlencode($to)), 'fr')->getHeaderLine('Location'), $to);
        }
        $this->assertSame('/en', $switch(new ServerRequest('GET', '/locale/xx'), 'xx')->getHeaderLine('Location'), 'unknown locales fall back');
    }

    public function testLocalizedValues(): void
    {
        $title = new LocalizedValue(['en' => 'Launch', 'fr' => 'Lancement', 'sw' => '  ']);

        $this->assertSame('Lancement', $title->in('fr'));
        $this->assertNull($title->in('sw'), 'blank is missing');
        $this->assertSame('Launch', $title->in('sw', fallback: 'en'));
        $this->assertSame(['sw'], $title->missing(['en', 'fr', 'sw']));
        $this->assertSame('{"en":"Launch","fr":"Lancement","sw":"Uzinduzi"}', json_encode($title->with('sw', 'Uzinduzi'), JSON_THROW_ON_ERROR));
    }

    private function middleware(): LocaleMiddleware
    {
        $locales = new Locales(['en', 'fr']);

        return new LocaleMiddleware($locales, new LocaleContext($locales), 'locale', redirectRoot: true, responses: new Psr17Factory());
    }
}

final readonly class Plain implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response();
    }
}
