<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

/**
 * @psalm-type Lifetime = DependencyContainer::SINGLETON|DependencyContainer::TRANSIENT
 */
class DependencyContainer implements DependencyContainerInterface
{
    use DependencyContainerSingletonTrait;
    use DependencyContainerTransientTrait;

    public const SINGLETON = 1;
    public const TRANSIENT = 2;

    private DependencyInjector $injector;

    /** @var array<class-string, DependencyDescriptor> */
    private array $factories = [];

    /** @var array<array{int,DependencyContainerInterface}> */
    private array $containers = [];

    /** @var array<string, int> */
    private array $namespaces = [];

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

    protected function getInjector(): DependencyInjectorInterface
    {
        return $this->injector;
    }

    /**
     * @param string $namespace
     * @param int $lifetime
     *
     * @return void
     */
    protected function addNamespace(string $namespace, int $lifetime): void
    {
        $this->namespaces[$namespace] = $lifetime;
    }

    /**
     * @param DependencyContainerInterface $container
     * @param int $lifetime
     *
     * @return void
     */
    protected function addContainer(DependencyContainerInterface $container, int $lifetime): void
    {
        $this->containers[] = [$lifetime, $container];
    }

    /**
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addSingletonContainer(DependencyContainerInterface $container): static
    {
        $this->addContainer($container, self::SINGLETON);

        return $this;
    }

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addSingletonNamespace(string $namespace): static
    {
        $this->addNamespace($namespace, self::SINGLETON);

        return $this;
    }

    /**
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addTransientContainer(DependencyContainerInterface $container): static
    {
        $this->addContainer($container, self::TRANSIENT);

        return $this;
    }

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addTransientNamespace(string $namespace): static
    {
        $this->addNamespace($namespace, self::TRANSIENT);

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
            $this->tryGetFromContainer($className, $instance) ||
            $this->tryGetFromNamespace($className, $instance)) {
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
        return $this->hasFactory($className) || $this->hasContainer($className) || $this->hasNamespace($className);
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
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
     *
     * @return bool
     * @throws CircularDependencyException
     * @throws DependencyInjectionException
     */
    private function tryGetFromNamespace(string $className, ?object &$instance): bool
    {
        return $this->resolveNamespaceClass($className) &&
            $this->tryGetFromFactory($className, $instance);
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return bool
     */
    private function hasNamespace(string $className): bool
    {
        return $this->resolveNamespaceClass($className);
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return bool
     */
    private function resolveNamespaceClass(string $className): bool
    {
        $namespacePrefix = $className;

        do {
            $separatorIndex = mb_strrpos($namespacePrefix, '\\');
            $namespacePrefix = $separatorIndex !== false ? mb_substr($namespacePrefix, 0, $separatorIndex) : '';

            if (array_key_exists($namespacePrefix, $this->namespaces)) {
                switch ($this->namespaces[$namespacePrefix]) {
                    case self::SINGLETON:
                        $this->addSingletonClass($className);
                        return true;

                    case self::TRANSIENT:
                        $this->addTransientClass($className);
                        return true;
                }
            }
        } while ($namespacePrefix !== '');

        return false;
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
        foreach ($this->containers as [$lifetime, $container]) {
            if ($container->has($className)) {
                switch ($lifetime) {
                    case self::SINGLETON:
                        $this->addSingletonFactory($className, fn() => $container->get($className));
                        return true;

                    case self::TRANSIENT:
                        $this->addTransientFactory($className, fn() => $container->get($className));
                        return true;
                }
            }
        }

        return false;
    }
}
