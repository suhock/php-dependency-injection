<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;

/**
 * @template TInterface
 */
class ImplementationContainer implements DependencyContainerInterface
{
    /** @var Closure(class-string<TInterface>):(TInterface|null) $factory */
    private readonly Closure $factory;

    /**
     * @param class-string<TInterface> $interfaceName
     * @param DependencyInjectorInterface $injector
     * @param null|callable(class-string<TInterface>):(TInterface|null) $factory
     */
    public function __construct(
        private readonly string $interfaceName,
        private readonly DependencyInjectorInterface $injector,
        ?callable $factory = null
    ) {
        $this->factory = $factory !== null ?
            $factory(...) :
            /**
             * @param class-string<TInterface> $className
             * @return TInterface
             */
            fn(string $className) => $this->injector->instantiate($className);
    }

    /**
     * @inheritDoc
     * @template TDependency of TInterface
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
     * @param class-string<TDependency> $className

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
