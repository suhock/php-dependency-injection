<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use FiveTwo\DependencyInjection\DependencyContainer;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\DependencyInjectorInterface;
use FiveTwo\DependencyInjection\UnresolvedClassException;

class ContextContainer
{
    public const DEFAULT_CONTEXT_STACK = [Context::DEFAULT];

    /** @var array<string, DependencyContainer> */
    private array $containers = [];

    private DependencyInjectorInterface $injector;

    public function __construct()
    {
        $this->injector = new ContextInjector($this);
        $this->containers[Context::DEFAULT] = $this->createContainer();
    }

    private function createContainer(): DependencyContainer
    {
        return new DependencyContainer($this->injector);
    }

    /**
     * @return DependencyInjectorInterface
     */
    public function getInjector(): DependencyInjectorInterface
    {
        return $this->injector;
    }

    public function addContext(string $name, DependencyContainer $container): static
    {
        $this->containers[$name] = $container;

        return $this;
    }

    public function getContext(string $name): DependencyContainer
    {
        return $this->containers[$name] ?? throw new DependencyInjectionException("Undefined container: $name");
    }

    public function context(string $name = Context::DEFAULT): DependencyContainer
    {
        return $this->containers[$name] ??= $this->createContainer();
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param array<string> $contextStack
     *
     * @return object|null
     */
    public function get(string $className, array $contextStack = self::DEFAULT_CONTEXT_STACK): ?object
    {
        for ($contextName = end($contextStack); $contextName !== false; $contextName = prev($contextStack)) {
            if ($this->hasInContext($className, $contextName)) {
                return $this->getFromContext($className, $contextName);
            }
        }

        throw new UnresolvedClassException($className);
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param array $contextStack
     *
     * @return bool
     */
    public function has(string $className, array $contextStack = self::DEFAULT_CONTEXT_STACK): bool
    {
        for ($contextName = end($contextStack); $contextName !== false; $contextName = prev($contextStack)) {
            if ($this->hasInContext($className, $contextName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param string $contextName
     *
     * @return object|null
     */
    private function getFromContext(string $className, string $contextName): ?object
    {
        return $this->hasInContext($className, $contextName) ?
            $this->containers[$contextName]->get($className) :
            throw new UnresolvedClassException($className);
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param string $contextName
     *
     * @return bool
     */
    private function hasInContext(string $className, string $contextName): bool
    {
        return isset($this->containers[$contextName]) && $this->containers[$contextName]->has($className);
    }
}
