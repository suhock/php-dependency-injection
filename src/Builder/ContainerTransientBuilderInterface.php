<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

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
     * - a `callable`: a factory to call.
     *
     * Where those overlap, the reading that does not need $source to be callable wins. A string naming an existing
     * class is an implementation, not a function to call.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|callable|null $source
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service, if they implement {@see \Suhock\Disposable\DisposableInterface}. Pass false to retain disposal
     *     responsibility (e.g., when a supplied instance is shared with code outside the container).
     *
     *@throws ImplementationException If $source names a class that is not a subclass of $className
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
     * Adds a transient service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `callable`: a factory to call.
     *
     * Where those overlap, the reading that does not need $source to be callable wins. A string naming an existing
     * class is an implementation, not a function to call.
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
}
