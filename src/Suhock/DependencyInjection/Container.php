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
use Closure;
use Suhock\DependencyInjection\Builder\ContainerBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerBuilderTrait;
use Suhock\DependencyInjection\Builder\ContainerScopedBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerScopedBuilderTrait;
use Suhock\DependencyInjection\Builder\ContainerSingletonBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerSingletonBuilderTrait;
use Suhock\DependencyInjection\Builder\ContainerTransientBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerTransientBuilderTrait;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Descriptor\ContainerDescriptor;
use Suhock\DependencyInjection\Descriptor\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\Lifetime\InstanceStore;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Throwable;
use UnitEnum;
use function is_string;
use function spl_object_id;

/**
 * A default implementation for the {@see ContainerInterface}.
 */
final class Container implements
    ContainerInterface,
    DisposableInterface,
    ScopeFactoryInterface,
    ContainerBuilderInterface,
    ContainerScopedBuilderInterface,
    ContainerSingletonBuilderInterface,
    ContainerTransientBuilderInterface
{
    use ContainerBuilderTrait;
    use ContainerScopedBuilderTrait;
    use ContainerSingletonBuilderTrait;
    use ContainerTransientBuilderTrait;

    /** @var array<string, Descriptor<object>> */
    protected array $descriptors = [];

    /** @var array<ContainerDescriptor> */
    protected array $containerDescriptors = [];

    private InjectorInterface $injector;

    /** @var Closure(ContainerInterface):InjectorInterface */
    private readonly Closure $injectorFactory;

    private readonly InstanceStore $instances;

    private readonly ResolutionContext $resolutionContext;

    /**
     * Descriptors currently being resolved, keyed by {@see spl_object_id()} of the descriptor. Reentry marks a
     * circular dependency. Shared by the container and all of its scopes, since one resolution chain may span several
     * resolution roots (a singleton's dependency graph always resolves in the root context, even when the singleton is
     * first requested from a scope).
     *
     * @var array<int, true>
     */
    private array $resolving = [];

    private bool $disposed = false;

    /**
     * @param callable(ContainerInterface):InjectorInterface $injectorFactory Provides the injector to be used in
     * conjunction with each resolution root (the container itself and each scope created from it).
     */
    public function __construct(callable $injectorFactory)
    {
        $this->injectorFactory = $injectorFactory(...);
        $this->injector = $injectorFactory($this);
        $this->instances = new InstanceStore();
        $this->resolutionContext = new ResolutionContext($this, $this->injector, $this->instances);
    }

    /**
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata.
     */
    public static function createDefault(?CacheInterface $cache = null): self
    {
        return new self(fn ($container) => Injector::createDefault($container, $cache));
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
    private function store(Descriptor $descriptor, string|UnitEnum|null $key): static
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
     * Computes the internal storage id for a service. Unkeyed services use the bare class name; keyed services use the
     * class name and key joined by a NUL byte, which cannot occur in a class name, so a keyed id can never collide with
     * an unkeyed one or with a different (class, key) pair.
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
     * Removes the specified service and any instance cached by this container, if they exist. Services of the same
     * class added under other keys are unaffected.
     *
     * Note: A service satisfied by a nested container (see {@see addContainer()}) is added back the next time it is
     * resolved, since the nested container still provides it. Instances already cached by existing scopes are
     * unaffected.
     *
     * Removal releases the container's cached instance without disposing it; an instance still referenced elsewhere
     * remains eligible for disposal when the container is disposed (see {@see dispose()}).
     *
     * @param class-string $className The class name of the service to remove
     * @param string|UnitEnum|null $key [optional] The key of the service to remove, or null for the unkeyed service
     *
     * @return $this
     */
    public function remove(string $className, string|UnitEnum|null $key = null): static
    {
        $id = $this->descriptorId($className, $key);

        if (isset($this->descriptors[$id])) {
            $this->instances->remove($this->descriptors[$id]->lifetimeStrategy);
            unset($this->descriptors[$id]);
        }

        return $this;
    }

    /**
     * @inheritDoc
     * @throws ContainerException If the container has been disposed
     */
    public function createScope(): ScopeInterface
    {
        $this->ensureNotDisposed();

        return new Scope($this, $this->injectorFactory, $this->resolutionContext);
    }

    /**
     * Disposes the container. Container-owned disposable singletons — and any container-owned disposable transients
     * still referenced that were resolved directly from the container — are disposed in reverse creation order
     * (dependents before their dependencies). Any subsequent request to the container ({@see get()}, {@see has()},
     * {@see createScope()}) throws a {@see ContainerException}. Disposing an already disposed container has no effect.
     *
     * Scopes created by this container are managed by their own caller and are not disposed here; dispose them before
     * disposing the container, otherwise their scoped instances are not swept. If a disposed instance throws, disposal
     * of the remaining instances still proceeds and the first exception is rethrown once the sweep completes.
     *
     * @throws Throwable The first exception thrown by any disposed instance
     */
    public function dispose(): void
    {
        if ($this->disposed) {
            return;
        }

        // Mark disposed before sweeping so that a disposer requesting a service fails fast rather than resurrecting
        // instances from a store that is being torn down.
        $this->disposed = true;
        $this->instances->dispose();
    }

    /**
     * @throws ContainerException If the container has been disposed
     */
    private function ensureNotDisposed(): void
    {
        if ($this->disposed) {
            throw new ContainerException('Container has been disposed');
        }
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
        return $this->getForContext($className, $key, $this->resolutionContext);
    }

    /**
     * Resolves a service on behalf of a resolution root — the container itself or one of its scopes.
     *
     * @template TClass of object
     * @param class-string<TClass> $className
     * @param ResolutionContext $context The context of the resolution root the service is being resolved for
     * @return TClass
     * @throws CircularDependencyException
     * @throws ClassNotFoundException
     * @throws ContainerException If the container has been disposed
     *
     * @internal
     */
    public function getForContext(string $className, string|UnitEnum|null $key, ResolutionContext $context): object
    {
        $this->ensureNotDisposed();

        if ($key !== null) {
            if ($this->tryGetFromDescriptor($this->descriptorId($className, $key), $context, $instance)) {
                /** @var TClass $instance */
                return $instance;
            }

            throw new ClassNotFoundException($className);
        }

        if ($this->tryGetFromDescriptor($className, $context, $instance) ||
            $this->tryGetFromContainer($className, $context, $instance)) {
            /** @var TClass $instance */
            return $instance;
        }

        throw new ClassNotFoundException($className);
    }

    /**
     * @inheritDoc
     * @throws ContainerException If the container has been disposed
     */
    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        $this->ensureNotDisposed();

        if ($key !== null) {
            return isset($this->descriptors[$this->descriptorId($className, $key)]);
        }

        return isset($this->descriptors[$className]) || $this->hasContainerDescriptor($className);
    }

    /**
     * @param string $id The service id, as produced by {@see descriptorId()}
     * @throws CircularDependencyException
     */
    private function tryGetFromDescriptor(string $id, ResolutionContext $context, ?object &$instance): bool
    {
        if (!isset($this->descriptors[$id])) {
            return false;
        }

        $instance = $this->resolveDescriptor($this->descriptors[$id], $context);

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
    private function resolveDescriptor(Descriptor $descriptor, ResolutionContext $context): object
    {
        $descriptorId = spl_object_id($descriptor);

        if (isset($this->resolving[$descriptorId])) {
            throw new CircularDependencyException($descriptor->className);
        }

        $this->resolving[$descriptorId] = true;

        try {
            return $descriptor->lifetimeStrategy->get(
                $context,
                static function (ResolutionContext $ctx) use ($descriptor): object {
                    $instance = $descriptor->instanceProvider->get($ctx);

                    // The strategy invokes this factory with the context whose store bounds the instance's lifetime
                    // (root store for singletons, scope store for scoped, requesting root's store for transients), so
                    // recording there disposes the instance exactly when that lifetime ends.
                    if ($descriptor->shouldDispose && $instance instanceof DisposableInterface) {
                        $ctx->store->addDisposable($instance);
                    }

                    return $instance;
                }
            );
        } catch (DependencyInjectionException $e) {
            throw new ClassResolutionException($descriptor->className, previous: $e);
        } finally {
            unset($this->resolving[$descriptorId]);
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
    private function tryGetFromContainer(string $className, ResolutionContext $context, ?object &$instance): bool
    {
        return $this->tryAddFromFirstMatchingContainer($className) &&
            $this->tryGetFromDescriptor($className, $context, $instance);
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
                fn () => $descriptor->container->get($className)
            )
        );

        return true;
    }
}
