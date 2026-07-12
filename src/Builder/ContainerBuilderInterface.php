<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use UnitEnum;

/**
 * Interface for building a dependency container.
 */
interface ContainerBuilderInterface
{
    /**
     * Adds an instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to add
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service; pass false when their disposal is the responsibility of something outside the container
     *
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true
    ): static;

    /**
     * Adds a keyed instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to add
     * @param string|UnitEnum $key The key of the service
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service; pass false when their disposal is the responsibility of something outside the container
     *
     * @return $this
     */
    public function addKeyed(
        string $className,
        string|UnitEnum $key,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true
    ): static;

    /**
     * Removes the specified service and any instance cached by this container, if they exist. Services of the same
     * class added under other keys are unaffected.
     *
     * Note: A service satisfied by a nested container (see {@see addContainer()}) is added back the next time it is
     * resolved, since the nested container still provides it. Instances already cached by existing scopes are
     * unaffected.
     *
     * Removal releases the container's cached instance without disposing it; an instance still referenced elsewhere
     * remains eligible for disposal when the container is disposed.
     *
     * @param class-string $className The class name of the service to remove
     * @param string|UnitEnum|null $key [optional] The key of the service to remove, or null for the unkeyed service
     *
     * @return $this
     */
    public function remove(string $className, string|UnitEnum|null $key = null): static;

    /**
     * Adds a nested container with a factory for generating lifetime strategies to manage instances within the outer
     * container. Nested containers are searched sequentially in the order they are added.
     *
     * @param ContainerInterface $container The nested container to add
     * @param callable(class-string):LifetimeStrategy<object> $lifetimeStrategyFactory A factory method for generating
     * lifetime strategies to manage instances within the container being built
     *
     * @return $this
     */
    public function addContainer(ContainerInterface $container, callable $lifetimeStrategyFactory): static;

    /**
     * @template TBuilder of self
     *
     * @param callable(TBuilder):mixed $configure
     *
     * @return $this
     */
    public function configure(callable $configure): static;
}
