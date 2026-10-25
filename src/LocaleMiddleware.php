<?php

declare(strict_types=1);

namespace Zana\Translation;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sets the request's locale from the first path segment when it is a
 * supported locale (/fr/...), otherwise from Accept-Language, otherwise the
 * fallback. The locale is the "_locale" request attribute and the current
 * LocaleContext for the duration of the request. The response gets
 * Content-Language, and Vary: Accept-Language when the header decided.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Locales $locales,
        private LocaleContext $context,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$locale, $negotiated] = $this->detect($request);

        $response = $this->context->run($locale, static fn (): ResponseInterface => $handler->handle($request->withAttribute('_locale', $locale)));

        if (!$response->hasHeader('Content-Language')) {
            $response = $response->withHeader('Content-Language', $locale);
        }
        $vary = array_map('trim', explode(',', strtolower($response->getHeaderLine('Vary'))));
        if ($negotiated && !in_array('accept-language', $vary, true) && !in_array('*', $vary, true)) {
            $response = $response->withAddedHeader('Vary', 'Accept-Language');
        }

        return $response;
    }

    /**
     * The locale of a request, and whether Accept-Language decided it (rather
     * than the path). Error pages use it, as they render outside this middleware.
     *
     * @return array{string, bool}
     */
    public function detect(ServerRequestInterface $request): array
    {
        $segment = explode('/', ltrim($request->getUri()->getPath(), '/'))[0];
        $locale = $this->locales->find($segment);

        return $locale === null
            ? [$this->locales->negotiate($request->getHeaderLine('Accept-Language')), true]
            : [$locale, false];
    }
}
