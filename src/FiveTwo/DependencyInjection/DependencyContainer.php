<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Lifetime\SingletonStrategy;
use FiveTwo\DependencyInjection\Lifetime\TransientStrategy;

class DependencyContainer implements DependencyContainerInterface
{
    use DependencyContainerSingletonTrait;
    use DependencyContainerTransientTrait;

    private DependencyInjector $injector;

    /** @var array<class-string, DependencyDescriptor> */
    private array $factories = [];

    /** @var array<ContainerDescriptor> */
    private array $containers = [];

    public function __construct()
    {
        $this->addSingletonInstance(self::class, $this);
        $this->injector = new DependencyInjector($this);
    }

    /**
     * @template TDependency
     *
     * @param DependencyDescriptor<TDependency> $descriptor
     *
     * @return $this
     */
    public function addDescriptor(DependencyDescriptor $descriptor): static
    {
        $this->factories[$descriptor->getClassName()] = $descriptor;

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
     * @param ContainerDescriptor $descriptor
     *
     * @return void
     */
    protected function addContainer(ContainerDescriptor $descriptor): void
    {
        $this->containers[] = $descriptor;
    }

    /**
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addSingletonContainer(DependencyContainerInterface $container): static
    {
        $this->addContainer(new ContainerDescriptor(
            $container,
            $this->injector,
            fn($className) => new SingletonStrategy($className)
        ));

        return $this;
    }

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addSingletonNamespace(string $namespace): static
    {
        $this->addSingletonContainer(new NamespaceContainer(
            $namespace,
            /** @param class-string $className */
            fn(string $className) => $this->injector->instantiate($className)
        ));

        return $this;
    }

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName): static
    {
        $this->addSingletonContainer(new ImplementationContainer(
            $interfaceName,
            /** @param class-string $className */
            fn(string $className) => $this->injector->instantiate($className)
        ));

        return $this;
    }

    /**
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addTransientContainer(DependencyContainerInterface $container): static
    {
        $this->addContainer(new ContainerDescriptor(
            $container,
            $this->injector,
            fn($className) => new TransientStrategy($className)
        ));

        return $this;
    }

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addTransientNamespace(string $namespace): static
    {
        $this->addTransientContainer(new NamespaceContainer(
            $namespace,
            /** @param class-string $className */
            fn(string $className) => $this->injector->instantiate($className)
        ));

        return $this;
    }

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addTransientInterface(string $interfaceName): static
    {
        $this->addTransientContainer(new ImplementationContainer(
            $interfaceName,
            /** @param class-string $className */
            fn(string $className) => $this->injector->instantiate($className)
        ));

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
            $descriptor = $containerDescriptor->getDependencyDescriptor($className);

            if ($descriptor) {
                $this->addDescriptor($descriptor);

                return true;
            }
        }

        return false;
    }
}
