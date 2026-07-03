<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use BackedEnum;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Provider\ClosureInstanceProvider;
use UnitEnum;
use function is_string;

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

    /** @var array<string, Descriptor<object>> */
    protected array $descriptors = [];

    /** @var array<ContainerDescriptor> */
    protected array $containerDescriptors = [];

    private InjectorInterface $injector;

    /**
     * @param InjectorInterface|null $injector [optional] An existing injector to use for injecting dependencies into
     * factories
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata. Only applied when no
     * $injector is supplied; pass an {@see Cache\ApcuCache} to share reflection metadata across requests.
     */
    public function __construct(?InjectorInterface $injector = null, ?CacheInterface $cache = null)
    {
        $this->injector = $injector ?? new ContainerInjector($this, $cache);
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
        return $this->store($descriptor, null);
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     *
     * @return $this
     */
    protected function addKeyedDescriptor(Descriptor $descriptor, string|UnitEnum $key): static
    {
        return $this->store($descriptor, $key);
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     *
     * @return $this
     */
    private function store(Descriptor $descriptor, string|  UnitEnum|null $key): static
    {
        $id = $this->descriptorId($descriptor->className, $key);

        if (isset($this->descriptors[$id])) {
            throw new ContainerException($key === null ?
                'Class already in container: ' . $descriptor->className :
                "Class already in container for key '" . self::getKeyFromStringOrEnum($key) . "': " .
                    $descriptor->className);
        }

        $this->descriptors[$id] = $descriptor;

        return $this;
    }

    private static function getKeyFromStringOrEnum(string|UnitEnum $key): string
    {
        return match (true) {
            $key instanceof BackedEnum && is_string($key->value) => $key->value,
            $key instanceof UnitEnum => $key->name,
            default => $key
        };
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
     * Computes the internal storage id for a registration. Unkeyed registrations use the bare class name; keyed
     * registrations use the class name and key joined by a NUL byte, which cannot occur in a class name, so a keyed id
     * can never collide with an unkeyed one or with a different (class, key) pair.
     *
     * @param class-string $className
     */
    private function descriptorId(string $className, string|UnitEnum|null $key): string
    {
        if ($key === null) {
            return $className;
        }

        $stringKey = self::getKeyFromStringOrEnum($key);

        return $className . "\0" . $stringKey;
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
    public function get(string $className, string|UnitEnum|null $key = null): object
    {
        if ($key !== null) {
            if ($this->tryGetFromDescriptor($this->descriptorId($className, $key), $instance)) {
                /** @var TClass $instance */
                return $instance;
            }

            throw new ClassNotFoundException($className);
        }

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
    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        if ($key !== null) {
            return isset($this->descriptors[$this->descriptorId($className, $key)]);
        }

        return isset($this->descriptors[$className]) || $this->hasContainerDescriptor($className);
    }

    /**
     * @param string $id The service id, as produced by {@see descriptorId()}
     * @throws CircularDependencyException
     */
    private function tryGetFromDescriptor(string $id, ?object &$instance): bool
    {
        if (!isset($this->descriptors[$id])) {
            return false;
        }

        $instance = $this->resolveDescriptor($this->descriptors[$id]);

        return true;
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     *
     * @return TClass
     * @throws CircularDependencyException
     */
    private function resolveDescriptor(Descriptor $descriptor): object
    {
        if ($descriptor->isResolving) {
            throw new CircularDependencyException($descriptor->className);
        }

        $descriptor->isResolving = true;

        try {
            $instanceFactory = $descriptor->instanceProvider->get(...);

            return $descriptor->lifetimeStrategy->get($instanceFactory);
        } catch (DependencyInjectionException $e) {
            throw new ClassResolutionException($descriptor->className, previous: $e);
        } finally {
            $descriptor->isResolving = false;
        }
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
     * @param class-string $className
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
