<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;

/**
 * Provides instances of classes that inherit from the specified interface or base class.
 *
 * @template TInterface
 */
class ImplementationContainer implements ContainerInterface
{
    /**
     * @var Closure $factory
     * @psalm-var Closure(class-string<TInterface>):(TInterface|null) $factory
     */
    private readonly Closure $factory;

    /**
     * @param class-string<TInterface> $interfaceName The name of the interface or base class
     * @param InjectorInterface $injector The injector to use for calling the instance factory
     * @param null|callable $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function(class-string&lt;T implements TInterface&gt; $className, ...): null|TInterface
     * </code>
     * @psalm-param null|callable(class-string<TInterface>):(TInterface|null) $factory
     */
    public function __construct(
        private readonly string $interfaceName,
        private readonly InjectorInterface $injector,
        ?callable $factory = null
    ) {
        $this->factory = $factory !== null ?
            $factory(...) :
            /**
             * @param class-string<TInterface> $className
             * @return TInterface
             */
            fn (string $className) => $this->injector->instantiate($className);
    }

    /**
     * @inheritDoc
     * @template TDependency of TInterface
     *
     * @psalm-param class-string<TDependency> $className
     * @psalm-return null|TDependency
     *
     * @throws UnresolvedClassException If the specified class does not implement or extend {@see $interfaceName}
     */
    public function get(string $className): ?object
    {
        return $this->has($className) ?
            $this->injector->call($this->factory, [$className]) :
            throw new UnresolvedClassException($className);
    }

    /**
     * @inheritDoc
     * @template TDependency of TInterface
     *
     * @psalm-param class-string<TDependency> $className
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
