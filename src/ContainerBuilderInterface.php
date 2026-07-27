<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\InstanceProvider\ImplementationException;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use UnitEnum;

/**
 * Interface for building a dependency container.
 */
interface ContainerBuilderInterface
{
    /**
     * Adds a singleton service. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     * - any other `object`: the instance to use.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     * @throws InstanceTypeException If $source is an object that is not an instance of $className
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addSingleton(
        string $className,
        string|callable|object|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a scoped service. A scoped service is instantiated once per {@see ScopeInterface} and its dependencies are
     * resolved from the scope; requesting one with no scope active throws a {@see ScopeException}.
     *
     * The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addScoped(
        string $className,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a transient service. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addTransient(
        string $className,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a singleton service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     * - any other `object`: the instance to use.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TClass>|TClass|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     * @throws InstanceTypeException If $source is an object that is not an instance of $className
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|callable|object|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a scoped service under $key. A scoped service is instantiated once per {@see ScopeInterface} and its
     * dependencies are resolved from the scope; requesting one with no scope active throws a {@see ScopeException}.
     *
     * The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TClass>|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Adds a transient service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TClass>|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     * @throws ImplementationException If $source names a class that is not a subclass of $className
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static;

    /**
     * Removes the specified service and any instance cached by this container, if they exist. Services of the same
     * class added under other keys are unaffected.
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
     * @template TBuilder of self
     *
     * @param callable(TBuilder):mixed $configure
     *
     * @return $this
     */
    public function configure(callable $configure): static;
}
