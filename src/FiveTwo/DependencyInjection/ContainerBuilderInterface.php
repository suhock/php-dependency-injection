<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * @psalm-import-type LifetimeStrategyFactory from ContainerDescriptor
 */
interface ContainerBuilderInterface
{
    /**
     * @template TDependency
     *
     * Adds an instance factory with a lifetime strategy to the container for a given class.
     *
     * @param class-string<TDependency> $className The name of the class to add
     * @param LifetimeStrategy<TDependency> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceFactory<TDependency> $instanceFactory The instance factory to use to create new instances
     *
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceFactory $instanceFactory
    ): static;

    /**
     * Adds a nested container with a factory for generating lifetime strategies to manage instances within
     * <em>this</em> container. Nested containers are searched sequentially in the order they are added.
     *
     * @template T
     *
     * @param ContainerInterface $container The nested container to add
     * @param callable $lifetimeStrategyFactory A factory methods for generating lifetime strategies to manage instances
     * within the container being built
     * @psalm-param LifetimeStrategyFactory $lifetimeStrategyFactory
     * @return $this
     */
    public function addContainer(ContainerInterface $container, callable $lifetimeStrategyFactory): static;
}
