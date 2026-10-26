<?php

declare(strict_types=1);

namespace Zana\Translation;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sets the request's locale, in order: the first path segment when it is a
 * supported locale (/fr/...); the locale cookie the language switcher sets
 * (translation.cookie, FE-26); Accept-Language; the fallback. The locale is
 * the "_locale" request attribute and the current LocaleContext for the
 * duration of the request.
 *
 * The response gets Content-Language, and Vary: Accept-Language (and Cookie,
 * with a locale cookie) when the URL did not decide. With $redirectRoot, a
 * request for "/" is redirected (302, never permanent) to "/<locale>".
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Locales $locales,
        private LocaleContext $context,
        private ?string $cookie = null,
        private bool $redirectRoot = false,
        private ?ResponseFactoryInterface $responses = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$locale, $negotiated] = $this->detect($request);

        if ($this->redirectRoot && $this->responses !== null && $request->getUri()->getPath() === '/') {
            $base = $request->getAttribute('base_path', '');

            return $this->vary($this->responses->createResponse(302)->withHeader('Location', (is_string($base) ? $base : '') . '/' . $locale));
        }

        $response = $this->context->run($locale, static fn (): ResponseInterface => $handler->handle($request->withAttribute('_locale', $locale)));

        if (!$response->hasHeader('Content-Language')) {
            $response = $response->withHeader('Content-Language', $locale);
        }

        return $negotiated ? $this->vary($response) : $response;
    }

    /**
     * The locale of a request, and whether something other than the path
     * decided it. Error pages use it, as they render outside this middleware.
     *
     * @return array{string, bool}
     */
    public function detect(ServerRequestInterface $request): array
    {
        $segment = explode('/', ltrim($request->getUri()->getPath(), '/'))[0];
        $locale = $this->locales->find($segment);
        if ($locale !== null) {
            return [$locale, false];
        }

        if ($this->cookie !== null) {
            $chosen = $request->getCookieParams()[$this->cookie] ?? null;
            $chosen = is_string($chosen) ? $this->locales->find($chosen) : null;
            if ($chosen !== null) {
                return [$chosen, true];
            }
        }

        return [$this->locales->negotiate($request->getHeaderLine('Accept-Language')), true];
    }

    private function vary(ResponseInterface $response): ResponseInterface
    {
        $vary = array_map('trim', explode(',', strtolower($response->getHeaderLine('Vary'))));
        if (in_array('*', $vary, true)) {
            return $response;
        }
        foreach ($this->cookie === null ? ['Accept-Language'] : ['Accept-Language', 'Cookie'] as $header) {
            if (!in_array(strtolower($header), $vary, true)) {
                $response = $response->withAddedHeader('Vary', $header);
            }
        }

        return $response;
    }
}
