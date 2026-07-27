<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Closure;
use Override;
use ReflectionClass;
use ReflectionParameter;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\ContextInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviders;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Lifetime\InstanceStore;
use Suhock\DependencyInjection\Resolver\DependencyResolver;
use Suhock\DependencyInjection\Resolver\ParameterResolutionException;
use Suhock\DependencyInjection\Resolver\ResolutionPlan;
use Suhock\DependencyInjection\Resolver\ResolutionPlanEdge;
use Suhock\DependencyInjection\Resolver\ResolutionPlanKind;
use Suhock\Disposable\DisposableInterface;
use Throwable;
use UnitEnum;

use function class_exists;
use function spl_object_id;

/**
 * The immutable product of {@see ContainerBuilder::build()}: resolves, scopes, and disposes services by executing
 * the resolution plans compiled at build time. The descriptor map and plan set never change after construction; no
 * dependency graph knowledge is derived at resolution time.
 */
final class Container implements
    ContainerInterface,
    DisposableInterface,
    ScopeFactoryInterface,
    ConcreteClassNameProviderInterface
{
    /** @var array<string, Descriptor<object>> */
    private readonly array $descriptors;

    /** @var array<string, ResolutionPlan<object>> */
    private readonly array $plans;

    /**
     * The factory and mutator closures paired with each plan, extracted once from the descriptors' providers so
     * execution does not rebuild dependency-source DTOs per resolution.
     *
     * @var array<string, Closure(mixed...):object|Closure(object, mixed...):mixed>
     */
    private array $closures = [];

    private readonly InstanceStore $instances;

    private readonly ResolutionContext $resolutionContext;

    /**
     * Descriptors currently being resolved, keyed by {@see spl_object_id()} of the descriptor. Reentry marks a
     * circular dependency. Shared by the container and all of its scopes, since one resolution chain may span several
     * resolution roots (a singleton's dependency graph always resolves in the root context, even when the singleton is
     * first requested from a scope). Build-time validation reports cycles it can prove; this guard backstops cycles
     * hidden inside factory bodies that resolve from the container.
     *
     * @var array<int, true>
     */
    private array $resolving = [];

    private bool $disposed = false;

    /**
     * @internal Use {@see ContainerBuilder::build()}
     *
     * @param array<string, Descriptor<object>> $descriptors The service descriptors, keyed by descriptor id
     * @param array<string, ResolutionPlan<object>> $plans The compiled resolution plans, keyed by descriptor id
     */
    public function __construct(array $descriptors, array $plans)
    {
        $this->descriptors = $descriptors;
        $this->plans = $plans;
        $this->instances = new InstanceStore();
        $this->resolutionContext = new ResolutionContext($this, $this->instances);

        foreach ($plans as $id => $plan) {
            $closure = self::executableClosure($plan, $descriptors[$id] ?? null);

            if ($closure !== null) {
                $this->closures[$id] = $closure;
            }
        }
    }

    /**
     * The factory or mutator closure a plan executes with, held by the descriptor's provider.
     *
     * @param ResolutionPlan<object> $plan
     * @param Descriptor<object>|null $descriptor
     *
     * @return Closure(mixed...):object|Closure(object, mixed...):mixed|null
     */
    private static function executableClosure(ResolutionPlan $plan, ?Descriptor $descriptor): ?Closure
    {
        $provider = $descriptor?->instanceProvider;

        return match (true) {
            InstanceProviders::isClosure($provider) => $provider->factory,
            InstanceProviders::isClass($provider) => $provider->mutator,
            default => null,
        };
    }

    /**
     * @inheritDoc
     *
     * @throws ContainerDisposedException If the container has been disposed
     */
    #[Override]
    public function createScope(): ScopeInterface
    {
        $this->ensureNotDisposed();

        return new Scope(
            $this,
            $this->resolutionContext,
            fn(string $className, string|UnitEnum|null $key, ResolutionContext $context): object
                => $this->getForContext($className, $key, $context),
        );
    }

    /**
     * Disposes the container. Container-owned disposable singletons (and any container-owned disposable transients
     * still referenced that were resolved directly from the container) are disposed in reverse creation order
     * (dependents before their dependencies). Any subsequent request to the container ({@see get()}, {@see has()},
     * {@see createScope()}) throws a {@see ContainerDisposedException}. Disposing an already disposed container has no effect.
     *
     * Scopes created by this container are managed by their own caller and are not disposed here; dispose them before
     * disposing the container, otherwise their scoped instances are not swept. If a disposed instance throws, disposal
     * of the remaining instances still proceeds and the first exception is rethrown once the sweep completes.
     *
     * @throws Throwable The first exception thrown by any disposed instance
     */
    #[Override]
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
     * @throws ContainerDisposedException If the container has been disposed
     */
    private function ensureNotDisposed(): void
    {
        if ($this->disposed) {
            throw new ContainerDisposedException('Container has been disposed');
        }
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @throws CircularDependencyException
     * @throws ClassNotFoundException
     *
     * @return TClass
     */
    #[Override]
    public function get(string $className, string|UnitEnum|null $key = null): object
    {
        return $this->getForContext($className, $key, $this->resolutionContext);
    }

    /**
     * Resolves a service on behalf of a resolution root (the container itself or one of its scopes).
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param ResolutionContext $context The context of the resolution root the service is being resolved for
     * @param bool $lazy Whether to produce a lazy object that defers the service's construction until first use
     *
     * @throws CircularDependencyException
     * @throws ClassNotFoundException
     * @throws ContainerDisposedException If the container has been disposed
     *
     * @return TClass
     */
    private function getForContext(
        string $className,
        string|UnitEnum|null $key,
        ResolutionContext $context,
        bool $lazy = false,
    ): object {
        $this->ensureNotDisposed();

        $id = DescriptorId::compute($className, $key);

        if (isset($this->descriptors[$id])) {
            /** @var TClass */
            return $this->resolveDescriptor($id, $this->descriptors[$id], $context, $lazy);
        }

        throw new ClassNotFoundException($className);
    }

    /**
     * @inheritDoc
     *
     * @throws ContainerDisposedException If the container has been disposed
     */
    #[Override]
    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        $this->ensureNotDisposed();

        return isset($this->descriptors[DescriptorId::compute($className, $key)]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getConcreteClassName(string $className, string|UnitEnum|null $key = null): ?string
    {
        return $this->concreteClassName(DescriptorId::compute($className, $key), []);
    }

    /**
     * Walks a service's compiled plan to the concrete class it produces, following implementation targets. Reads
     * only the plan, so it never constructs anything.
     *
     * @param array<string, true> $seen Descriptor ids already visited, to break an implementation cycle
     *
     * @return class-string|null
     */
    private function concreteClassName(string $id, array $seen): ?string
    {
        if (isset($seen[$id]) || !isset($this->plans[$id])) {
            return null;
        }

        $seen[$id] = true;
        $plan = $this->plans[$id];

        return match ($plan->kind) {
            ResolutionPlanKind::AutowiredClass => self::concreteClass($plan->className),
            ResolutionPlanKind::Factory => self::concreteClass($plan->declaredFactoryReturnType)
                ?? self::concreteClass($plan->className),
            ResolutionPlanKind::Implementation => $plan->implementationTarget === null
                ? null
                : $this->concreteClassName(DescriptorId::compute($plan->implementationTarget, null), $seen),
            ResolutionPlanKind::Leaf => self::leafConcreteClass($this->descriptors[$id] ?? null),
        };
    }

    /**
     * The given name when it is a concrete, instantiable class; <code>null</code> for interfaces, abstract classes,
     * and absent or non-class names.
     *
     * @return class-string|null
     */
    private static function concreteClass(?string $className): ?string
    {
        return $className !== null && class_exists($className) && new ReflectionClass($className)->isInstantiable()
            ? $className
            : null;
    }

    /**
     * The concrete class of a leaf service: the held instance's class, known without constructing it. A
     * context-dependent selector produces its instance only at resolution time, so its class is not statically known.
     *
     * @param Descriptor<object>|null $descriptor
     *
     * @return class-string|null
     */
    private static function leafConcreteClass(?Descriptor $descriptor): ?string
    {
        $provider = $descriptor?->instanceProvider;

        return $provider instanceof ObjectInstanceProvider ? $provider->instance::class : null;
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     * @param bool $lazy Whether to produce a lazy object that defers the service's construction until first use
     *
     * @throws CircularDependencyException
     *
     * @return TClass
     */
    private function resolveDescriptor(
        string $id,
        Descriptor $descriptor,
        ResolutionContext $context,
        bool $lazy = false,
    ): object {
        $descriptorId = spl_object_id($descriptor);

        if (isset($this->resolving[$descriptorId])) {
            throw new CircularDependencyException($descriptor->className);
        }

        $this->resolving[$descriptorId] = true;

        try {
            /** @var TClass */
            return $descriptor->lifetimeStrategy->get(
                $context,
                // Plan execution produces an instance of the descriptor's class (by construction for autowired
                // classes, by instanceof guard for factories and held instances), but the generic cannot flow
                // through the compiled plan.
                // @phpstan-ignore argument.type (executed plan yields the descriptor's TClass)
                function (ResolutionContext $ctx) use ($id, $descriptor, $lazy): object {
                    $instance = $this->executePlan($id, $descriptor, $ctx, $lazy);

                    // The strategy invokes this factory with the context whose store bounds the instance's lifetime
                    // (root store for singletons, scope store for scoped, requesting root's store for transients), so
                    // recording there disposes the instance exactly when that lifetime ends.
                    if ($descriptor->shouldDispose && $instance instanceof DisposableInterface) {
                        $ctx->store->addDisposable($instance);
                    }

                    return $instance;
                },
            );
        } catch (DependencyInjectionException $e) {
            throw new ClassResolutionException($descriptor->className, previous: $e);
        } finally {
            unset($this->resolving[$descriptorId]);
        }
    }

    /**
     * Produces a service's instance by executing its compiled plan against the given resolution context. When
     * <code>$lazy</code>, autowired classes and factories yield a native lazy object (a ghost the container
     * initializes itself, or a proxy backed by the factory) that defers construction until first use; an
     * implementation forwards laziness to its target; a leaf is already constructed and is returned as-is.
     *
     * @param Descriptor<object> $descriptor
     */
    private function executePlan(string $id, Descriptor $descriptor, ResolutionContext $ctx, bool $lazy): object
    {
        $plan = $this->plans[$id] ?? throw new ContainerException("No compiled plan exists for service $id");

        return match ($plan->kind) {
            ResolutionPlanKind::AutowiredClass => $this->executeAutowiredClass($id, $plan, $ctx, $lazy),
            ResolutionPlanKind::Factory => $this->executeFactory($id, $plan, $ctx, $lazy),
            ResolutionPlanKind::Implementation => $this->executeImplementation($plan, $ctx, $lazy),
            ResolutionPlanKind::Leaf => self::executeLeaf($descriptor->instanceProvider, $ctx),
        };
    }

    /**
     * Produces an edge-less service's instance: a held instance, or one derived from the resolution context.
     *
     * @param InstanceProviderInterface<object> $provider
     */
    private static function executeLeaf(InstanceProviderInterface $provider, ResolutionContext $ctx): object
    {
        if ($provider instanceof ObjectInstanceProvider) {
            return $provider->instance;
        }

        if ($provider instanceof ContextInstanceProvider) {
            $instance = ($provider->select)($ctx);

            if (!$instance instanceof $provider->className) {
                throw new InstanceTypeException($provider->className, $instance);
            }

            return $instance;
        }

        throw new ContainerException('Unknown leaf instance provider ' . $provider::class);
    }

    /**
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     *
     * @return TClass
     */
    private function executeAutowiredClass(string $id, ResolutionPlan $plan, ResolutionContext $ctx, bool $lazy): object
    {
        if ($lazy) {
            $rClass = new ReflectionClass($plan->className);

            return $rClass->newLazyGhost(function (object $ghost) use ($id, $plan, $ctx, $rClass): void {
                $rClass->getConstructor()?->invokeArgs(
                    $ghost,
                    $this->resolveArguments($plan->argumentEdges, $ctx, [$plan->className, '__construct']),
                );
                $this->applyMutator($id, $plan, $ctx, $ghost);
            });
        }

        $className = $plan->className;
        $instance = new $className(...$this->resolveArguments($plan->argumentEdges, $ctx, [$className, '__construct']));
        $this->applyMutator($id, $plan, $ctx, $instance);

        return $instance;
    }

    /**
     * Applies the descriptor's configuration mutator to a newly produced instance, if one is paired with the plan.
     *
     * @param ResolutionPlan<object> $plan
     */
    private function applyMutator(string $id, ResolutionPlan $plan, ResolutionContext $ctx, object $instance): void
    {
        $mutator = $this->closures[$id] ?? null;

        if ($mutator !== null) {
            $mutator($instance, ...$this->resolveArguments($plan->mutatorEdges, $ctx, $mutator, skip: 1));
        }
    }

    /**
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     *
     * @return TClass
     */
    private function executeFactory(string $id, ResolutionPlan $plan, ResolutionContext $ctx, bool $lazy): object
    {
        /** @var Closure(mixed...):TClass $factory (guaranteed to be TClass factory) */
        $factory = $this->closures[$id]
            ?? throw new ContainerException("No factory closure is paired with the plan for $plan->className");

        if ($lazy) {
            // A factory produces the object opaquely, so the container cannot construct it in place; it wraps the
            // factory in a proxy of the statically-known concrete class, forwarding to the factory's result on first
            // use. Build-time validation guarantees a concrete class exists here.
            $className = self::lazyProxyClass($plan)
                ?? throw new ContainerException(
                    "Cannot lazily resolve $plan->className: its concrete class is not statically known",
                );

            return (new ReflectionClass($className))->newLazyProxy(
                /**
                 * @template TClass of object
                 *
                 * @param TClass $proxy
                 *
                 * @return TClass
                 */
                fn(object $proxy): object => $this->invokeFactory($plan, $factory, $ctx),
            );
        }

        return $this->invokeFactory($plan, $factory, $ctx);
    }

    /**
     * Invokes a factory with its resolved arguments and verifies the result is an instance of the service class.
     *
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     * @param Closure(mixed...):TClass $factory
     *
     * @return TClass
     */
    private function invokeFactory(ResolutionPlan $plan, Closure $factory, ResolutionContext $ctx): object
    {
        $result = $factory(...$this->resolveArguments($plan->argumentEdges, $ctx, $factory));

        if (!$result instanceof $plan->className) {
            throw new InstanceTypeException($plan->className, $result);
        }

        return $result;
    }

    /**
     * The statically known concrete, instantiable class a lazy proxy of a factory-produced service can reflect: the
     * factory's declared return class if concrete, otherwise the service's own class if concrete. <code>null</code>
     * when neither is known, which build-time validation rejects.
     *
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     *
     * @return class-string<TClass>|null
     */
    private static function lazyProxyClass(ResolutionPlan $plan): ?string
    {
        if (
            $plan->declaredFactoryReturnType !== null
            && class_exists($plan->declaredFactoryReturnType)
            && new ReflectionClass($plan->declaredFactoryReturnType)->isInstantiable()
        ) {
            return $plan->declaredFactoryReturnType;
        }

        if (class_exists($plan->className) && new ReflectionClass($plan->className)->isInstantiable()) {
            return $plan->className;
        }

        return null;
    }

    /**
     * @template TClass of object
     *
     * @param ResolutionPlan<TClass> $plan
     *
     * @return TClass
     */
    private function executeImplementation(ResolutionPlan $plan, ResolutionContext $ctx, bool $lazy): object
    {
        if ($plan->implementationTarget === null) {
            throw new ContainerException("The plan for $plan->className names no implementation");
        }

        return $lazy
            ? $this->getForContext($plan->implementationTarget, null, $ctx, lazy: true)
            : $ctx->container->get($plan->implementationTarget);
    }

    /**
     * Resolves a group of parameter edges to call arguments, in order.
     *
     * @param list<ResolutionPlanEdge> $edges
     * @param array{class-string, string}|Closure(mixed...):object|Closure(object, mixed...):mixed $functionRef The
     *     reflectable reference to the parameters' function, used only to build a precise exception when a required
     *     edge fails
     * @param int $skip The parameter offset of the first edge within the referenced function's signature
     *
     * @return list<mixed>
     */
    private function resolveArguments(
        array $edges,
        ResolutionContext $ctx,
        array|Closure $functionRef,
        int $skip = 0,
    ): array {
        $args = [];

        foreach ($edges as $index => $edge) {
            try {
                if (!$this->tryResolveEdge($edge, $ctx, $value)) {
                    throw new ParameterResolutionException(new ReflectionParameter($functionRef, $index + $skip));
                }
            } catch (ClassResolutionException $exception) {
                throw new ParameterResolutionException(
                    new ReflectionParameter($functionRef, $index + $skip),
                    $exception,
                );
            }

            $args[] = $value;
        }

        return $args;
    }

    /**
     * Resolves one edge: the first candidate present in the descriptor map whose instance satisfies its whole
     * conjunction wins; a soft edge falls back to its default value or <code>null</code> on failure, mirroring the
     * self-healing the injector applies to defaulted and nullable injection points.
     *
     * @param mixed $value Receives the resolved value or the soft fallback
     *
     * @throws ClassResolutionException If a required edge's candidate failed while resolving its own dependencies
     *
     * @return bool Whether a value was produced; <code>false</code> only for a required edge that cannot be satisfied
     */
    private function tryResolveEdge(ResolutionPlanEdge $edge, ResolutionContext $ctx, mixed &$value): bool
    {
        $dependency = $edge->dependency;

        if ($dependency !== null) {
            try {
                // A lazy edge produces the winning candidate as a native lazy object; the fetcher routes through the
                // container's own plan execution so the lazy instance is still cached under the target's lifetime.
                $instance = $edge->lazy
                    ? DependencyResolver::resolve(
                        $dependency,
                        $ctx->container,
                        fn(string $className, string|UnitEnum|null $key): object
                            => $this->getForContext($className, $key, $ctx, lazy: true),
                    )
                    : DependencyResolver::resolve($dependency, $ctx->container);

                if ($instance !== null) {
                    $value = $instance;

                    return true;
                }
            } catch (ClassResolutionException $exception) {
                // A candidate failed while resolving its own graph: a soft edge self-heals; a required edge defers
                // the failure to the caller, which wraps it with the injection point's identity.
                if (!$edge->soft) {
                    throw $exception;
                }
            }
        }

        if ($edge->soft) {
            $value = $edge->hasDefault ? $edge->defaultValue : null;

            return true;
        }

        return false;
    }
}
