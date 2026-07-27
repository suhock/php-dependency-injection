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
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use UnitEnum;

/**
 * Interface for adding singleton factories to a container.
 */
interface ContainerSingletonBuilderInterface
{
    /**
     * Adds a singleton service. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     * - an `object`: the instance to use.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|Closure|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     * @throws InstanceTypeException If $source is an object that is not an instance of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addSingleton(
        string $className,
        string|object|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a singleton service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     * - an `object`: the instance to use.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TClass>|TClass|Closure|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     * @throws InstanceTypeException If $source is an object that is not an instance of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class by calling the specified
     * factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     *     Any method parameters will be injected.
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addSingletonFactory(string $className, callable $factory, bool $shouldDispose = true): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class under the given key by
     * calling the specified factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     *     Any method parameters will be injected.
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility yourself, e.g. when a supplied instance is shared with code outside the container.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedSingletonFactory(
        string $className,
        string|UnitEnum $key,
        callable $factory,
        bool $shouldDispose = true,
    ): static;
}
