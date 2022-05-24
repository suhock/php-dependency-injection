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
     *
     * @return DependencyDescriptor<TDependency>|null
     */
    public function getDependencyDescriptor(string $className): ?DependencyDescriptor
    {
        return $this->container->has($className) ?
            new DependencyDescriptor(
                $className,
                ($this->lifetimeStrategyFactory)($className),
                new ClosureInstanceFactory(
                    $className,
                    fn() => $this->container->get($className),
                    $this->injector
                )
            ) :
            null;
    }
}
