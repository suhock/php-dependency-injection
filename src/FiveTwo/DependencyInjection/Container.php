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

use FiveTwo\DependencyInjection\InstanceProvision\InstanceProvider;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * A default implementation for the {@see ContainerInterface}.
 */
class Container implements ContainerInterface, ContainerBuilder
{
    use ContainerSingletonBuilderTrait;
    use ContainerTransientBuilderTrait;

    private InjectorInterface $injector;

    /** @var array<class-string, Descriptor<object>> */
    private array $factories = [];

    /** @var array<ContainerDescriptor> */
    private array $containers = [];

    /**
     * @param InjectorInterface|null $injector [Optional] An existing injector to use for injecting dependencies into
     * factories
     */
    public function __construct(?InjectorInterface $injector = null)
    {
        $this->injector = $injector ?? new Injector($this);
    }

    protected function getInjector(): InjectorInterface
    {
        return $this->injector;
    }

    /**
     * @inheritDoc
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProvider $instanceProvider
    ): static {
        $this->factories[$className] = new Descriptor($className, $lifetimeStrategy, $instanceProvider);

        return $this;
    }

    /**
     * Removes the specified factory and/or instance if they exist.
     *
     * @param class-string $className
     *
     * @return $this
     */
    public function remove(string $className): static
    {
        unset($this->factories[$className]);

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @phpstan-ignore-next-line PHPStan does not support callable-level generics but complains that LifetimeStrategy
     * does not have its generic type specified
     */
    public function addContainer(ContainerInterface $container, callable $lifetimeStrategyFactory): static
    {
        $this->containers[] = new ContainerDescriptor($container, $this->injector, $lifetimeStrategyFactory);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function build(callable $builder): static
    {
        $builder($this);

        return $this;
    }

    /**
     * @inheritDoc
     * @throws CircularDependencyException
     * @throws UnresolvedClassException
     */
    public function get(string $className): ?object
    {
        if ($this->tryGetFromFactory($className, $instance) ||
            $this->tryGetFromContainer($className, $instance)) {
            return $instance;
        }

        throw new UnresolvedClassException($className);
    }

    /**
     * @inheritDoc
     */
    public function has(string $className): bool
    {
        return $this->hasFactory($className) || $this->hasContainer($className);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param TClass|null $instance
     *
     * @return bool
     * @throws CircularDependencyException
     */
    private function tryGetFromFactory(string $className, ?object &$instance): bool
    {
        if (!$this->hasFactory($className)) {
            return false;
        }

        $instance = $this->getFactory($className)->getInstance();

        return true;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return Descriptor<TClass>
     * @psalm-suppress InvalidReturnType Psalm does not support class-mapped arrays
     */
    private function getFactory(string $className): Descriptor
    {
        /**
         * @psalm-suppress InvalidReturnStatement Psalm does not support class-mapped arrays
         * @phpstan-ignore-next-line PHPStan does not support class-mapped arrays
         */
        return $this->factories[$className];
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return bool
     */
    private function hasFactory(string $className): bool
    {
        return array_key_exists($className, $this->factories);
    }

    /**
     * @template TClass as object
     *
     * @param class-string<TClass> $className
     * @param TClass|null $instance
     *
     * @return bool
     * @throws CircularDependencyException
     */
    private function tryGetFromContainer(string $className, ?object &$instance): bool
    {
        return $this->hasContainer($className) &&
            $this->tryGetFromFactory($className, $instance);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return bool
     */
    private function hasContainer(string $className): bool
    {
        foreach ($this->containers as $containerDescriptor) {
            if ($containerDescriptor->tryAdd($className, $this)) {
                return true;
            }
        }

        return false;
    }
}
