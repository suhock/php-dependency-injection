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
 * Provides a {@see ContainerInterface} aggregate for resolving instances based on the current context.
 *
 * @template TContainer of ContainerInterface
 */
class ContextContainer implements ContainerInterface, InjectorProvider
{
    private readonly InjectorInterface $injector;

    /** @var array<string, TContainer> */
    private array $containers = [];

    /** @var list<string> */
    private array $stack = [];

    /**
     * @param Closure(InjectorProvider):TContainer $containerFactory A factory method for creating new container
     * instances
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
     * @return InjectorInterface An injector backed by this container
     */
    public function getInjector(): InjectorInterface
    {
        return $this->injector;
    }

    /**
     * Returns a container by name. If the container does not already exist a new container instance with the specified
     * name will be added.
     *
     * @param string $name The name of the container
     *
     * @return TContainer The container identified by the specified name
     */
    public function context(string $name = Context::DEFAULT): ContainerInterface
    {
        return $this->containers[$name] ??= $this->createContainer();
    }

    /**
     * Pushes a context onto the current context stack by name.
     *
     * @param string $name The name of the context to push on the stack
     *
     * @return $this
     */
    public function push(string $name): static
    {
        $this->stack[] = $name;

        return $this;
    }

    /**
     * Pops the most recently pushed context off the stack and returns it. The original default context will never be
     * removed from the stack.
     *
     * @return string
     */
    public function pop(): string
    {
        return count($this->stack) > 1 ? array_pop($this->stack) : $this->stack[0];
    }

    /**
     * @return list<string> The current context stack
     */
    public function getStack(): array
    {
        return $this->stack;
    }

    /**
     * Resets the context stack to a state containing only the default context.
     *
     * @return $this
     */
    public function resetStack(): static
    {
        $this->stack = [Context::DEFAULT];

        return $this;
    }

    /**
     * Retrieves an object or <code>null</code> from the container identified by its class name, prioritizing
     * contexts by last pushed.
     *
     * @template TDependency
     *
     * @param class-string<TDependency> $className The name of the class to retrieve
     *
     * @return TDependency|null An instance of {@see $className} or <code>null</code>
     * @throws UnresolvedClassException If a value could not be resolved for the class
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
     * @inheritDoc
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
