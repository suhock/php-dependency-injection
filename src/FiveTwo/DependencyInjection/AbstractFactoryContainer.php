<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;

/**
 * Abstract base class for containers that calls a common factory method for any class in the container, as indicated by
 * the {@see has} method. Child classes simply need to implement the {@see has} method.
 */
abstract class AbstractFactoryContainer implements ContainerInterface
{
    private readonly Closure $factory;

    protected readonly InjectorInterface $injector;

    /**
     * @param InjectorInterface|null $injector [optional] The injector to use for calling the factory method
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function&lt;TClass&gt;(class-string&lt;TClass&gt; $className, [object ...]): TClass
     * </code>
     *
     * @psalm-mutation-free
     */
    public function __construct(
        ?InjectorInterface $injector = null,
        ?callable $factory = null
    ) {
        $this->injector = $injector ?? new Injector($this);
        $this->factory = $factory !== null ?
            $factory(...) :
            $this->injector->instantiate(...);
    }

    /**
     * @inheritDoc
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to retrieve
     *
     * @return TClass An instance of {@see $className}
     * @throws UnresolvedClassException If the specified class does not implement or extend {@see $interfaceName}
     *
     * @psalm-suppress MixedInferredReturnType Psalm cannot infer a return type from a generic return type on a callable
     */
    public function get(string $className): object
    {
        /**
         * @psalm-suppress MixedReturnStatement Psalm cannot infer a return type from a generic return type on a
         * callable
         */
        return $this->has($className) ?
            $this->injector->call($this->factory, [$className]) :
            throw new UnresolvedClassException($className);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to test
     *
     * @return bool <code>true</code> if the container can provide a value, <code>false</code> otherwise
     * @psalm-mutation-free
     */
    abstract public function has(string $className): bool;
}
