<?php

declare(strict_types=1);

namespace Zana\Translation;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The language switcher's target (FE-26): remembers the visitor's choice in
 * the locale cookie, then redirects to the page in that language.
 *
 *     $router->get('/locale/{locale}', LocaleSwitchAction::class)->name('locale.switch');
 *     // <a href="/locale/fr?to=/fr/a-propos">Français</a>
 *
 * "to" must be a path on this site; anything else redirects to "/<locale>".
 */
final readonly class LocaleSwitchAction
{
    public function __construct(
        private Locales $locales,
        private ResponseFactoryInterface $responses,
        private string $cookie = 'locale',
    ) {
    }

    public function __invoke(ServerRequestInterface $request, string $locale): ResponseInterface
    {
        $locale = $this->locales->find($locale) ?? $this->locales->fallback();
        $base = $request->getAttribute('base_path', '');
        $base = is_string($base) ? $base : '';

        $to = $request->getQueryParams()['to'] ?? null;
        $safe = is_string($to) && preg_match('#^/(?![/\\\\])[^\s]*$#', $to) === 1;

        $cookie = sprintf('%s=%s; Path=%s; Max-Age=31536000; SameSite=Lax', $this->cookie, $locale, $base === '' ? '/' : $base);
        if ($request->getUri()->getScheme() === 'https') {
            $cookie .= '; Secure';
        }

        return $this->responses->createResponse(303)
            ->withHeader('Location', $safe ? $to : $base . '/' . $locale)
            ->withHeader('Set-Cookie', $cookie)
            ->withHeader('Cache-Control', 'no-store');
    }
}
