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

use FiveTwo\DependencyInjection\Provision\ClosureInstanceProvider;

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
        $this->injector = $injector ?? new Injector($this);
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
        if ($this->hasDescriptor($descriptor->getClassName())) {
            throw new ContainerException('Class already in container: ' . $descriptor->getClassName());
        }

        $this->descriptors[$descriptor->getClassName()] = $descriptor;

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
     * @throws UnresolvedClassException
     */
    public function get(string $className): object
    {
        if ($this->tryGetFromFactory($className, $instance) ||
            $this->tryGetFromContainer($className, $instance)) {
            return $instance;
        }

        throw new UnresolvedClassException($className);
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
    private function tryGetFromFactory(string $className, ?object &$instance): bool
    {
        if (!$this->hasDescriptor($className)) {
            return false;
        }

        $instance = $this->getDescriptor($className)->getInstance();

        return true;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return Descriptor<TClass>
     * @psalm-suppress InvalidReturnType Psalm does not support class-mapped arrays
     * @psalm-mutation-free
     */
    protected function getDescriptor(string $className): Descriptor
    {
        /**
         * @psalm-suppress InvalidReturnStatement Psalm does not support class-mapped arrays
         * @phpstan-ignore-next-line PHPStan does not support class-mapped arrays
         */
        return $this->descriptors[$className];
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return bool
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
     *
     * @param class-string<TClass> $className
     * @param TClass|null $instance
     *
     * @return bool
     * @throws CircularDependencyException
     */
    private function tryGetFromContainer(string $className, ?object &$instance): bool
    {
        return $this->tryAddFromFirstMatchingContainer($className) &&
            $this->tryGetFromFactory($className, $instance);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return bool
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
     * @template TClass
     * @param class-string<TClass> $className
     */
    private function tryAdd(string $className, ContainerDescriptor $descriptor): bool
    {
        if (!$descriptor->container->has($className)) {
            return false;
        }

        $this->add(
            $className,
            /** @phpstan-ignore-next-line PHPStan gets confused resolving generic for add() */
            $descriptor->createLifetimeStrategy($className),
            new ClosureInstanceProvider(
                $className,
                /** @phpstan-ignore-next-line PHPStan gets confused resolving generic for add() */
                fn () => $descriptor->container->get($className),
                $this->injector
            )
        );

        return true;
    }
}
