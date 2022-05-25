<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

class DependencyContainer implements DependencyContainerInterface, ContainerBuilderInterface,
    SingletonContainerBuilderInterface, TransientContainerBuilderInterface
{
    use SingletonContainerBuilderTrait;
    use TransientContainerBuilderTrait;

    private DependencyInjectorInterface $injector;

    /** @var array<class-string, DependencyDescriptor> */
    private array $factories = [];

    /** @var array<ContainerDescriptor> */
    private array $containers = [];

    public function __construct(?DependencyInjectorInterface $injector = null)
    {
        $this->addSingletonInstance(self::class, $this);
        $this->injector = $injector ?? new DependencyInjector($this);
    }

    /**
     * @inheritDoc
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceFactory $instanceFactory
    ): static {
        $this->factories[$className] = new DependencyDescriptor($className, $lifetimeStrategy, $instanceFactory);

        return $this;
    }

    /**
     * @return DependencyInjectorInterface A dependency injector backed by this container
     */
    public function getInjector(): DependencyInjectorInterface
    {
        return $this->injector;
    }

    /**
     * @inheritDoc
     */
    public function addContainer(DependencyContainerInterface $container, Closure $lifetimeStrategyFactory): static
    {
        $this->containers[] = new ContainerDescriptor($container, $this->injector, $lifetimeStrategyFactory);

        return $this;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return TDependency|null
     * @throws CircularDependencyException
     * @throws UnresolvedClassException
     * @throws DependencyInjectionException
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
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return bool
     */
    public function has(string $className): bool
    {
        return $this->hasFactory($className) || $this->hasContainer($className);
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
     *
     * @return bool
     * @throws CircularDependencyException
     */
    private function tryGetFromFactory(string $className, ?object &$instance): bool
    {
        if (!$this->hasFactory($className)) {
            return false;
        }

        $instance = $this->factories[$className]->getDependency();

        return true;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return bool
     */
    private function hasFactory(string $className): bool
    {
        return array_key_exists($className, $this->factories);
    }

    /**
     * @template TDependency as object
     *
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
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
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return bool
     */
    private function hasContainer(string $className): bool
    {
        foreach ($this->containers as $containerDescriptor) {
            if ($containerDescriptor->tryAddDependency($className, $this)) {
                return true;
            }
        }

        return false;
    }
}
