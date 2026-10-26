<?php

declare(strict_types=1);

namespace Zana\Translation;

use Psr\Http\Message\ResponseFactoryInterface;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\ApplicationInterface;
use Zana\Core\ServiceProvider;
use Zana\Validation\MessageTranslatorInterface;

/**
 * Registers the locales (translation.locales; the first is the fallback),
 * the current locale, the catalogs, the translator, LocaleMiddleware
 * (translation.cookie remembers the visitor's choice, translation.redirect_root
 * redirects "/" to "/<locale>") and LocaleSwitchAction.
 *
 * At boot, each module's Resources/translations folder is added in module
 * order, then the application's catalogs (translation.path, default
 * <base>/translations), which therefore win. With mj-zana/validation
 * installed, validation messages are translated too.
 */
final class TranslationServiceProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(Locales::class, static function (ServiceResolverInterface $r): Locales {
                /** @var list<string> $locales */
                $locales = $r->get(ConfigurationInterface::class)->array('translation.locales', ['en']);

                return new Locales($locales);
            })
            ->singleton(LocaleContext::class)
            ->singleton(Catalogs::class)
            ->singleton(Translator::class)
            ->alias(TranslatorInterface::class, Translator::class)
            ->singleton(LocaleMiddleware::class, static function (ServiceResolverInterface $r): LocaleMiddleware {
                $config = $r->get(ConfigurationInterface::class);
                $locales = $r->get(Locales::class);
                $context = $r->get(LocaleContext::class);
                $responses = $r->has(ResponseFactoryInterface::class) ? $r->get(ResponseFactoryInterface::class) : null;
                assert($locales instanceof Locales && $context instanceof LocaleContext);

                return new LocaleMiddleware(
                    $locales,
                    $context,
                    $config->has('translation.cookie') ? $config->string('translation.cookie') : null,
                    $config->bool('translation.redirect_root', false),
                    $responses instanceof ResponseFactoryInterface ? $responses : null,
                );
            })
            ->transient(LocaleSwitchAction::class, static function (ServiceResolverInterface $r): LocaleSwitchAction {
                $locales = $r->get(Locales::class);
                $responses = $r->get(ResponseFactoryInterface::class);
                assert($locales instanceof Locales && $responses instanceof ResponseFactoryInterface);

                return new LocaleSwitchAction($locales, $responses, $r->get(ConfigurationInterface::class)->string('translation.cookie', 'locale'));
            });

        if (interface_exists(MessageTranslatorInterface::class) && !$services->has(MessageTranslatorInterface::class)) {
            $services->singleton(MessageTranslatorInterface::class, ValidationMessageTranslator::class);
        }
    }

    public function boot(ContainerInterface $container): void
    {
        $application = $container->get(ApplicationInterface::class);
        $catalogs = $container->get(Catalogs::class);
        assert($application instanceof ApplicationInterface && $catalogs instanceof Catalogs);

        foreach ($application->modules() as $module) {
            $catalogs->addDirectory($module->path('Resources', 'translations'));
        }
        $catalogs->addDirectory($application->config()->string('translation.path', $application->path('translations')));
    }

    public function origin(): string
    {
        return 'package:mj-zana/translation';
    }
}
