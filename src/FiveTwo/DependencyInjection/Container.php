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

use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;
use FiveTwo\DependencyInjection\Provision\ClosureInstanceProvider;

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
     * @param InjectorInterface|null $injector [Optional] An existing injector to use for injecting dependencies into
     * factories
     * @psalm-mutation-free
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
     * @psalm-external-mutation-free
     */
    protected function addDescriptor(Descriptor $descriptor): static
    {
        if ($this->hasDescriptor($descriptor->className)) {
            throw new ContainerException('Class already in container: ' . $descriptor->className);
        }

        $this->descriptors[$descriptor->className] = $descriptor;

        return $this;
    }

    /**
     * @psalm-external-mutation-free
     */
    protected function addContainerDescriptor(ContainerDescriptor $descriptor): static
    {
        $this->containerDescriptors[] = $descriptor;

        return $this;
    }

    /**
     * @psalm-mutation-free
     */
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
     * @psalm-external-mutation-free
     */
    public function remove(string $className): static
    {
        unset($this->descriptors[$className]);

        return $this;
    }

    /**
     * @inheritDoc
     * @throws CircularDependencyException
     * @throws ClassNotFoundException
     */
    public function get(string $className): object
    {
        if ($this->tryGetFromDescriptor($className, $instance) ||
            $this->tryGetFromContainer($className, $instance)) {
            return $instance;
        }

        throw new ClassNotFoundException($className);
    }

    /**
     * @inheritDoc
     * @psalm-mutation-free
     */
    public function has(string $className): bool
    {
        return $this->hasDescriptor($className) || $this->hasContainerDescriptor($className);
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
    private function tryGetFromDescriptor(string $className, ?object &$instance): bool
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
            /** @psalm-var Closure():TClass $instanceFactory Psalm doesn't resolve the template type on InstanceProvider
             * correctly */
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
     * @psalm-mutation-free
     */
    protected function getDescriptor(string $className): Descriptor
    {
        /**
         * @psalm-var Descriptor<TClass>[] $this->descriptors Psalm does not support class-mapped arrays
         * @phpstan-ignore-next-line PHPStan does not support class-mapped arrays
         */
        return $this->descriptors[$className];
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @psalm-mutation-free
     */
    private function hasDescriptor(string $className): bool
    {
        return array_key_exists($className, $this->descriptors);
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $className
     * @psalm-mutation-free
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
     * @param class-string<TClass> $className
     * @param TClass|null $instance
     * @throws CircularDependencyException
     */
    private function tryGetFromContainer(string $className, ?object &$instance): bool
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
