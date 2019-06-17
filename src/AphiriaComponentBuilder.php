<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/configuration/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Configuration;

use Aphiria\Api\ContainerDependencyResolver;
use Aphiria\Api\IDependencyResolver;
use Aphiria\Api\RouterKernel;
use Aphiria\Console\Commands\CommandRegistry;
use Aphiria\Routing\Builders\RouteBuilderRegistry;
use Aphiria\Routing\LazyRouteFactory;
use Opulence\Ioc\IContainer;

/**
 * Defines the component builder for Aphiria components
 */
final class AphiriaComponentBuilder
{
    /**
     * Registers Aphiria console commands
     *
     * @param IApplicationBuilder $appBuilder The app builder to register to
     * @return AphiriaComponentBuilder For chaining
     */
    public function withCommandComponent(IApplicationBuilder $appBuilder): self
    {
        $appBuilder->registerComponentFactory('commands', function (IContainer $container, array $callbacks) {
            if ($container->hasBinding(CommandRegistry::class)) {
                $commands = $container->resolve(CommandRegistry::class);
            } else {
                $container->bindInstance(CommandRegistry::class, $commands = new CommandRegistry());
            }

            foreach ($callbacks as $callback) {
                $callback($commands);
            }
        });

        return $this;
    }

    /**
     * Registers the Aphiria router
     *
     * @param IApplicationBuilder $appBuilder The app builder to register to
     * @return AphiriaComponentBuilder For chaining
     */
    public function withRouteComponent(IApplicationBuilder $appBuilder): self
    {
        $appBuilder->registerComponentFactory('routes', function (IContainer $container, array $callbacks) use ($appBuilder) {
            // Set up the router request handler
            if ($container->hasBinding(IDependencyResolver::class)) {
                $dependencyResolver = $container->resolve(IDependencyResolver::class);
            } else {
                $container->bindInstance(
                    IDependencyResolver::class,
                    $dependencyResolver = new ContainerDependencyResolver($container)
                );
            }

            $appBuilder->withRouter(fn (IContainer $container) => $container->resolve(RouterKernel::class));

            if ($container->hasBinding(LazyRouteFactory::class)) {
                $routeFactory = $container->resolve(LazyRouteFactory::class);
            } else {
                $container->bindInstance(LazyRouteFactory::class, $routeFactory = new LazyRouteFactory());
            }

            $routeFactory->addFactory(function () use ($callbacks) {
                $routeBuilders = new RouteBuilderRegistry();

                foreach ($callbacks as $callback) {
                    $callback($routeBuilders);
                }

                return $routeBuilders->buildAll();
            });
        });

        return $this;
    }
}
