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
 * Provides instances of classes that inherit from the given interface or base class.
 *
 * @template TInterface of object
 */
class InterfaceContainer implements ContainerInterface
{
    /**
     * @var Closure<TInterface, TInterface|null> $factory
     * @psalm-var Closure(class-string<TInterface>, mixed ...):(TInterface|null) $factory
     * @phpstan-var Closure(class-string<TInterface>, mixed ...):(TInterface|null) $factory
     */
    private readonly Closure $factory;

    /**
     * @param class-string<TInterface> $interfaceName The name of the interface or base class
     * @param InjectorInterface $injector The injector to use for calling the instance factory
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function(class-string&lt;T implements TInterface&gt; $className, ...): null|TInterface
     * </code>
     * @psalm-param null|callable(class-string<TInterface>, mixed ...):(TInterface|null) $factory
     * @phpstan-param null|callable(class-string<TInterface>, mixed ...):(TInterface|null) $factory
     */
    public function __construct(
        private readonly string $interfaceName,
        private readonly InjectorInterface $injector,
        ?callable $factory = null
    ) {
        /** @phpstan-ignore-next-line PHPStan does not support generics docs on anonymous functions */
        $this->factory = $factory !== null ?
            $factory(...) :
            /**
             * @param class-string<TInterface> $className
             * @return TInterface
             * @phpstan-ignore-next-line PHPStan does not support generics docs on anonymous functions
             */
            fn (string $className) => $this->injector->instantiate($className);
    }

    /**
     * @inheritDoc
     * @template TClass of TInterface
     *
     * @param class-string<TClass> $className The name of the class to retrieve
     *
     * @return TClass|null An instance of {@see $className} or <code>null</code>
     * @throws UnresolvedClassException If the specified class does not implement or extend {@see $interfaceName}
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
     * @template TClass of TInterface
     *
     * @psalm-param class-string<TClass> $className
     *
     * @psalm-suppress DocblockTypeContradiction: Cannot resolve types for $className - docblock-defined type
     * class-string<TInterface:FiveTwo\DependencyInjection\ImplementationContainer as object> does not contain
     * class-string<TInterface>
     * Not clear why Psalm has trouble resolving $className
     */
    public function has(string $className): bool
    {
        return is_subclass_of($className, $this->interfaceName);
    }
}
