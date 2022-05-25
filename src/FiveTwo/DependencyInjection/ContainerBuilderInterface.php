<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

interface ContainerBuilderInterface
{
    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param LifetimeStrategy<TDependency> $lifetimeStrategy
     * @param InstanceFactory<TDependency> $instanceFactory
     *
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceFactory $instanceFactory
    ): static;

    /**
     * @param DependencyContainerInterface $container
     * @param Closure(class-string):LifetimeStrategy $lifetimeStrategyFactory
     *
     * @return $this
     */
    public function addContainer(DependencyContainerInterface $container, Closure $lifetimeStrategyFactory): static;
}
