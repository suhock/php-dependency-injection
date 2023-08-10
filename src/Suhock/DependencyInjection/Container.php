<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Provision\ClosureInstanceProvider;

use function array_key_exists;

/**
 * A default implementation for the {@see ContainerInterface}.
 */
class Container implements
    ContainerInterface,
    ContainerBuilderInterface,
    ContainerSingletonBuilderInterface,
    ContainerTransientBuilderInterface
{
    use ContainerBuilderTrait;
    use ContainerSingletonBuilderTrait;
    use ContainerTransientBuilderTrait;

    /** @var array<class-string, Descriptor<object>> */
    protected array $descriptors = [];

    /** @var array<ContainerDescriptor> */
    protected array $containerDescriptors = [];

    private InjectorInterface $injector;

    /**
     * @param InjectorInterface|null $injector [optional] An existing injector to use for injecting dependencies into
     * factories
     */
    public function __construct(?InjectorInterface $injector = null)
    {
        $this->injector = $injector ?? new ContainerInjector($this);
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     *
     * @return $this
     */
    protected function addDescriptor(Descriptor $descriptor): static
    {
        if ($this->hasDescriptor($descriptor->className)) {
            throw new ContainerException('Class already in container: ' . $descriptor->className);
        }

        $this->descriptors[$descriptor->className] = $descriptor;

        return $this;
    }

    protected function addContainerDescriptor(ContainerDescriptor $descriptor): static
    {
        $this->containerDescriptors[] = $descriptor;

        return $this;
    }

    protected function getInjector(): InjectorInterface
    {
        return $this->injector;
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
        unset($this->descriptors[$className]);

        return $this;
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @return TClass
     * @throws CircularDependencyException
     * @throws ClassNotFoundException
     */
    public function get(string $className): object
    {
        if ($this->tryGetFromDescriptor($className, $instance) ||
            $this->tryGetFromContainer($className, $instance)) {
            /** @var TClass $instance */
            return $instance;
        }

        throw new ClassNotFoundException($className);
    }

    /**
     * @inheritDoc
     */
    public function has(string $className): bool
    {
        return $this->hasDescriptor($className) || $this->hasContainerDescriptor($className);
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param-out TClass|null $instance
     * @throws CircularDependencyException
     */
    private function tryGetFromDescriptor(string $className, mixed &$instance): bool
    {
        if (!$this->hasDescriptor($className)) {
            return false;
        }

        $descriptor = $this->getDescriptor($className);

        if ($descriptor->isResolving) {
            throw new CircularDependencyException($descriptor->className);
        }

        $descriptor->isResolving = true;

        try {
            $instanceFactory = $descriptor->instanceProvider->get(...);
            $instance = $descriptor->lifetimeStrategy->get($instanceFactory);
        } catch (DependencyInjectionException $e) {
            throw new ClassResolutionException($descriptor->className, previous: $e);
        } finally {
            $descriptor->isResolving = false;
        }

        return true;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return Descriptor<TClass>
     */
    protected function getDescriptor(string $className): Descriptor
    {
        /**
         * @phpstan-ignore-next-line PHPStan does not support class-mapped arrays
         */
        return $this->descriptors[$className];
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     */
    private function hasDescriptor(string $className): bool
    {
        return array_key_exists($className, $this->descriptors);
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     */
    private function hasContainerDescriptor(string $className): bool
    {
        foreach ($this->containerDescriptors as $descriptor) {
            if ($descriptor->container->has($className)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template TClass as object
     *
     * @param class-string<TClass> $className
     * @param mixed $instance
     * @param-out TClass|null $instance
     *
     * @return bool
     */
    private function tryGetFromContainer(string $className, mixed &$instance): bool
    {
        return $this->tryAddFromFirstMatchingContainer($className) &&
            $this->tryGetFromDescriptor($className, $instance);
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     */
    private function tryAddFromFirstMatchingContainer(string $className): bool
    {
        foreach ($this->containerDescriptors as $descriptor) {
            if ($this->tryAdd($className, $descriptor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     */
    private function tryAdd(string $className, ContainerDescriptor $descriptor): bool
    {
        if (!$descriptor->container->has($className)) {
            return false;
        }

        /** @var LifetimeStrategy<TClass> $lifetimeStrategy variable to aid with static analysis */
        $lifetimeStrategy = ($descriptor->lifetimeStrategyFactory)($className);

        $this->add(
            $className,
            $lifetimeStrategy,
            new ClosureInstanceProvider(
                $className,
                fn () => $descriptor->container->get($className),
                $this->injector
            )
        );

        return true;
    }
}
