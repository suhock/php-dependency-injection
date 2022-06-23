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
     */
    protected function addDescriptor(Descriptor $descriptor): static
    {
        if ($this->hasDescriptor($descriptor->getClassName())) {
            throw new ContainerException('Class already in container: ' . $descriptor->getClassName());
        }

        $this->descriptors[$descriptor->getClassName()] = $descriptor;

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
     */
    public function has(string $className): bool
    {
        return $this->hasDescriptor($className) || $this->hasContainer($className);
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
     */
    private function hasDescriptor(string $className): bool
    {
        return array_key_exists($className, $this->descriptors);
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
        foreach ($this->containerDescriptors as $containerDescriptor) {
            if ($containerDescriptor->tryAdd($className, $this)) {
                return true;
            }
        }

        return false;
    }
}
