<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use Closure;
use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\InjectorProvider;
use FiveTwo\DependencyInjection\UnresolvedClassException;

/**
 * @template TContainer of ContainerInterface
 */
class ContextContainer implements ContainerInterface, InjectorProvider
{
    public const DEFAULT_CONTEXT_STACK = [Context::DEFAULT];

    private readonly InjectorInterface $injector;

    /** @var array<string, TContainer> */
    private array $containers = [];

    /** @var list<string> */
    private array $stack;

    /**
     * @param Closure(InjectorProvider):TContainer $containerFactory
     */
    public function __construct(
        private readonly Closure $containerFactory
    ) {
        $this->injector = new ContextInjector($this);
        $this->containers[Context::DEFAULT] = $this->createContainer();
        $this->resetStack();
    }

    /**
     * @return TContainer
     */
    private function createContainer(): ContainerInterface
    {
        return ($this->containerFactory)($this);
    }

    /**
     * @return InjectorInterface
     */
    public function getInjector(): InjectorInterface
    {
        return $this->injector;
    }

    /**
     * @param string $name
     *
     * @return TContainer
     */
    public function context(string $name = Context::DEFAULT): ContainerInterface
    {
        return $this->containers[$name] ??= $this->createContainer();
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function push(string $name): static
    {
        $this->stack[] = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function pop(): string
    {
        return count($this->stack) > 1 ? array_pop($this->stack) : $this->stack[0];
    }

    /**
     * @return list<string>
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * @return $this
     */
    public function resetStack(): static
    {
        $this->stack = [Context::DEFAULT];

        return $this;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return TDependency|null
     */
    public function get(string $className): ?object
    {
        for ($contextName = end($this->stack); key($this->stack) !== null; $contextName = prev($this->stack)) {
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
     *
     * @return bool
     */
    public function has(string $className): bool
    {
        for ($contextName = end($this->stack); key($this->stack) !== null; $contextName = prev($this->stack)) {
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
     * @return TDependency|null
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
