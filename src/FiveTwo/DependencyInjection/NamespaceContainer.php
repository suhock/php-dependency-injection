<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;

class NamespaceContainer implements DependencyContainerInterface
{
    private readonly string $namespace;

    /** @var Closure(class-string):(object|null) */
    private readonly Closure $factory;

    /**
     * @param string $namespace
     * @param DependencyInjectorInterface $injector
     * @param null|callable(class-string):(object|null) $factory
     */
    public function __construct(
        string $namespace,
        private readonly DependencyInjectorInterface $injector,
        ?callable $factory = null
    ) {
        $this->namespace = trim($namespace, '\\');
        $this->factory = $factory !== null ?
            $factory(...) :
            /**
             * @param class-string $className
             * @return object
             */
            fn(string $className) => $this->injector->instantiate($className);
    }

    /**
     * @inheritDoc
     * @throws UnresolvedClassException
     */
    public function get(string $className): ?object
    {
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
