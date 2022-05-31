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
 * Provides instances of classes within the given namespace.
 */
class NamespaceContainer implements ContainerInterface
{
    private readonly string $namespace;

    /**
     * @var Closure
     * @psalm-var Closure(class-string, mixed ...):(object|null)
     */
    private readonly Closure $factory;

    /**
     * @param string $namespace The namespace from which to provide class instances
     * @param InjectorInterface $injector The injector to use for calling the instance factory
     * @param null|callable $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function(class-string&lt;T&gt; $className, ...): null|T
     * </code>
     * @psalm-param null|callable(class-string, mixed ...):(object|null) $factory
     */
    public function __construct(
        string $namespace,
        private readonly InjectorInterface $injector,
        ?callable $factory = null
    ) {
        $this->namespace = trim($namespace, '\\');

        /** @psalm-suppress PropertyTypeCoercion Psalm seems confused */
        $this->factory = $factory !== null ?
            $factory(...) :
            $this->injector->instantiate(...);
    }

    /**
     * @inheritDoc
     *
     * @throws UnresolvedClassException If the specified class is not in the namespace
     *
     * @psalm-suppress MixedInferredReturnType Psalm cannot infer a return type from a generic return type on a callable
     */
    public function get(string $className): ?object
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
     * @inheritDoc
     */
    public function has(string $className): bool
    {
        return $this->namespace === '' ||
            str_starts_with($className, $this->namespace . '\\');
    }
}
