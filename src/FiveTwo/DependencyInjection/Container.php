<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * A default implementation for the {@see ContainerInterface}.
 */
class Container implements
    ContainerInterface,
    ContainerBuilderInterface,
    ContainerSingletonBuilderInterface,
    ContainerTransientBuilderInterface,
    InjectorProvider
{
    use ContainerSingletonBuilderTrait;
    use ContainerTransientBuilderTrait;

    private InjectorInterface $injector;

    /** @var array<class-string, Descriptor> */
    private array $factories = [];

    /** @var array<ContainerDescriptor> */
    private array $containers = [];

    /**
     * @param InjectorProvider|null $injectorProvider [optional] The source of an existing injector to use for injecting
     * dependencies into factories
     */
    public function __construct(?InjectorProvider $injectorProvider = null)
    {
        $this->addSingletonInstance(self::class, $this);

        $this->injector = $injectorProvider?->getInjector() ?? new Injector($this);
        $this->addSingletonInstance($this->injector::class, $this->injector)
            ->addSingletonInstance(InjectorInterface::class, $this->injector);
    }

    /**
     * @inheritDoc
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceFactory $instanceFactory
    ): static {
        $this->factories[$className] = new Descriptor($className, $lifetimeStrategy, $instanceFactory);

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
     * @return InjectorInterface A dependency injector backed by this container
     */
    public function getInjector(): InjectorInterface
    {
        return $this->injector;
    }

    /**
     * @inheritDoc
     */
    public function addContainer(ContainerInterface $container, Closure $lifetimeStrategyFactory): static
    {
        $this->containers[] = new ContainerDescriptor($container, $this->injector, $lifetimeStrategyFactory);

        return $this;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
