<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Closure;
use Suhock\DependencyInjection\InstanceProvider\ImplementationException;
use UnitEnum;

/**
 * Interface for adding transient factories to a container.
 */
interface ContainerTransientBuilderInterface
{
    /**
     * Adds a transient service. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     *
     * A supplied instance is a single object, so it can only be a container-wide singleton; see
     * {@see ContainerSingletonBuilderInterface::addSingleton()}.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|Closure|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addTransient(
        string $className,
        string|Closure|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a transient service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     *
     * A supplied instance is a single object, so it can only be a container-wide singleton; see
     * {@see ContainerSingletonBuilderInterface::addKeyedSingleton()}.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TClass>|Closure|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Indicates that the container should provide a transient instance of the given class by calling the specified
     * factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by
     *     {@see $className}. Any method parameters will be injected.
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addTransientFactory(string $className, callable $factory, bool $shouldDispose = true): static;

    /**
     * Indicates that the container should provide a transient instance of the given class under the given key by
     * calling the specified factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable $factory A factory method that returns an instance of the class specified by
     *     {@see $className}. Any method parameters will be injected.
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedTransientFactory(
        string $className,
        string|UnitEnum $key,
        callable $factory,
        bool $shouldDispose = true,
    ): static;
}
