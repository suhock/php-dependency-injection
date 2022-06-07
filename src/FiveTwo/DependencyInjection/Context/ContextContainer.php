<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Context;

use Closure;
use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\UnresolvedClassException;

/**
 * Manages a collection of named {@see ContainerInterface} instances and resolves objects from them based on a context
 * stack
 *
 * @template TContainer of ContainerInterface
 */
class ContextContainer implements ContainerInterface
{
    private readonly InjectorInterface $injector;

    /** @var array<string, TContainer> */
    private array $containers = [];

    /** @var list<string> */
    private array $stack = [];

    /**
     * @param Closure(InjectorInterface):TContainer $containerFactory A factory method for creating new container
     * instances
     */
    public function __construct(
        private readonly Closure $containerFactory
    ) {
        /** @psalm-suppress MixedArgumentTypeCoercion Psalm cannot infer $this generic type in constructor */
        $this->injector = new ContextInjector($this);
    }

    /**
     * @return TContainer
     */
    private function createContainer(): ContainerInterface
    {
        return ($this->containerFactory)($this->injector);
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
    public function context(string $name): ContainerInterface
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
     * Pops the most recently pushed context off the stack and returns it.
     *
     * @return string
     */
    public function pop(): string
    {
        if (count($this->stack) === 0) {
            throw new DependencyInjectionException('Context stack is empty');
        }

        return array_pop($this->stack);
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
        $this->stack = [];

        return $this;
    }

    /**
     * Retrieves an object or <code>null</code> from the container identified by its class name, prioritizing
     * contexts by last pushed.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to retrieve
     *
     * @return TClass|null An instance of {@see $className} or <code>null</code>
     * @throws UnresolvedClassException If a value could not be resolved for the class
     */
    public function get(string $className): ?object
    {
        return $this->getFromContext(
            $className,
            $this->findContext($className) ?? throw new UnresolvedClassException($className)
        );
    }

    /**
     * @inheritDoc
     */
    public function has(string $className): bool
    {
        return $this->findContext($className) !== null;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return string|null
     */
    private function findContext(string $className): ?string
    {
        for ($contextName = end($this->stack); $contextName !== false; $contextName = prev($this->stack)) {
            if ($this->hasInContext($className, $contextName)) {
                return $contextName;
            }
        }

        return null;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string $contextName
     *
     * @return TClass|null
     */
    private function getFromContext(string $className, string $contextName): ?object
    {
        return $this->containers[$contextName]->get($className);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string $contextName
     *
     * @return bool
     */
    private function hasInContext(string $className, string $contextName): bool
    {
        return isset($this->containers[$contextName]) && $this->containers[$contextName]->has($className);
    }
}
