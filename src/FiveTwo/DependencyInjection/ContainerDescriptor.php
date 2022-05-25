<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

class ContainerDescriptor
{
    /**
     * @param DependencyContainerInterface $container
     * @param DependencyInjectorInterface $injector
     * @param Closure(class-string):LifetimeStrategy $lifetimeStrategyFactory
     */
    public function __construct(
        private readonly DependencyContainerInterface $container,
        private readonly DependencyInjectorInterface $injector,
        private readonly Closure $lifetimeStrategyFactory
    ) {
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param ContainerBuilderInterface $container
     *
     * @return bool
     */
    public function tryAddDependency(string $className, ContainerBuilderInterface $container): bool
    {
        if (!$this->container->has($className)) {
            return false;
        }

        $container->add(
            $className,
            ($this->lifetimeStrategyFactory)($className),
            new ClosureInstanceFactory(
                $className,
                fn() => $this->container->get($className),
                $this->injector
            )
        );

        return true;
    }
}
