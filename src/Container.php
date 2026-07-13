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
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ClosureInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\ContextInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Lifetime\InstanceStore;
use Suhock\DependencyInjection\Resolver\ParameterResolutionException;
use Suhock\DependencyInjection\Resolver\PropertyResolutionException;
use Suhock\DependencyInjection\Resolver\ResolutionPlan;
use Suhock\DependencyInjection\Resolver\ResolutionPlanEdge;
use Suhock\DependencyInjection\Resolver\ResolutionPlanKind;
use Throwable;
use UnitEnum;

use function get_class;
use function spl_object_id;

/**
 * The immutable product of {@see ContainerBuilder::build()}: resolves, scopes, and disposes services by executing
 * the resolution plans compiled at build time. The descriptor map and plan set never change after construction; no
 * dependency graph knowledge is derived at resolution time.
 */
final class Container implements ContainerInterface, DisposableInterface, ScopeFactoryInterface
{
    /** @var array<string, Descriptor<object>> */
    private readonly array $descriptors;

    /** @var array<string, ResolutionPlan> */
    private readonly array $plans;

    /**
     * The factory and mutator closures paired with each plan, extracted once from the descriptors' providers so
     * execution does not rebuild dependency-source DTOs per resolution.
     *
     * @var array<string, Closure>
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
     * @param array<string, ResolutionPlan> $plans The compiled resolution plans, keyed by descriptor id
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
     * @param Descriptor<object>|null $descriptor
     */
    private static function executableClosure(ResolutionPlan $plan, ?Descriptor $descriptor): ?Closure
    {
        $provider = $descriptor?->instanceProvider;

        return match (true) {
            $provider instanceof ClosureInstanceProvider => $provider->factory,
            $provider instanceof ClassInstanceProvider => $provider->mutator,
            default => null,
        };
    }

    /**
     * @inheritDoc
     * @throws ContainerException If the container has been disposed
     */
    public function createScope(): ScopeInterface
    {
        $this->ensureNotDisposed();

        return new Scope(
            $this,
            $this->resolutionContext,
            fn (string $className, string|UnitEnum|null $key, ResolutionContext $context): object =>
                $this->getForContext($className, $key, $context)
        );
    }

    /**
     * Disposes the container. Container-owned disposable singletons (and any container-owned disposable transients
     * still referenced that were resolved directly from the container) are disposed in reverse creation order
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
     */
    private function getForContext(string $className, string|UnitEnum|null $key, ResolutionContext $context): object
    {
        $this->ensureNotDisposed();

        $id = $this->descriptorId($className, $key);

        if (isset($this->descriptors[$id])) {
            /** @var TClass */
            return $this->resolveDescriptor($id, $this->descriptors[$id], $context);
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

        return isset($this->descriptors[$this->descriptorId($className, $key)]);
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

        $stringKey = Key::getKeyFromStringOrEnum($key);

        return $className . "\0" . $stringKey;
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     *
     * @return TClass
     * @throws CircularDependencyException
     */
    private function resolveDescriptor(string $id, Descriptor $descriptor, ResolutionContext $context): object
    {
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
                function (ResolutionContext $ctx) use ($id, $descriptor): object {
                    $instance = $this->executePlan($id, $descriptor, $ctx);

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
     * Produces a service's instance by executing its compiled plan against the given resolution context.
     *
     * @param Descriptor<object> $descriptor
     */
    private function executePlan(string $id, Descriptor $descriptor, ResolutionContext $ctx): object
    {
        $plan = $this->plans[$id] ?? throw new ContainerException("No compiled plan exists for service $id");

        return match ($plan->kind) {
            ResolutionPlanKind::AutowiredClass => $this->executeAutowiredClass($id, $plan, $ctx),
            ResolutionPlanKind::Factory => $this->executeFactory($id, $plan, $ctx),
            ResolutionPlanKind::Implementation => $this->executeImplementation($plan, $ctx),
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

        throw new ContainerException('Unknown leaf instance provider ' . get_class($provider));
    }

    private function executeAutowiredClass(string $id, ResolutionPlan $plan, ResolutionContext $ctx): object
    {
        $className = $plan->className;
        $args = $this->resolveArguments($plan->argumentEdges, $ctx, [$className, '__construct']);
        $instance = new $className(...$args);

        foreach ($plan->injectMethodEdges as $methodName => $edges) {
            (new ReflectionMethod($instance, $methodName))->invokeArgs(
                $instance,
                $this->resolveArguments($edges, $ctx, [$className, $methodName])
            );
        }

        foreach ($plan->injectPropertyEdges as $propertyName => $edge) {
            (new ReflectionProperty($className, $propertyName))
                ->setValue($instance, $this->resolvePropertyEdge($edge, $ctx, $className));
        }

        $mutator = $this->closures[$id] ?? null;

        if ($mutator !== null) {
            $mutator($instance, ...$this->resolveArguments($plan->mutatorEdges, $ctx, $mutator, skip: 1));
        }

        return $instance;
    }

    private function executeFactory(string $id, ResolutionPlan $plan, ResolutionContext $ctx): object
    {
        $factory = $this->closures[$id] ?? null;

        if ($factory === null) {
            throw new ContainerException("No factory closure is paired with the plan for $plan->className");
        }

        $result = $factory(...$this->resolveArguments($plan->argumentEdges, $ctx, $factory));

        if (!$result instanceof $plan->className) {
            throw new InstanceTypeException($plan->className, $result);
        }

        return $result;
    }

    private function executeImplementation(ResolutionPlan $plan, ResolutionContext $ctx): object
    {
        if ($plan->implementationTarget === null) {
            throw new ContainerException("The plan for $plan->className names no implementation");
        }

        return $ctx->container->get($plan->implementationTarget);
    }

    /**
     * Resolves a group of parameter edges to call arguments, in order.
     *
     * @param list<ResolutionPlanEdge> $edges
     * @param array{class-string, string}|Closure $functionRef The reflectable reference to the parameters' function,
     * used only to build a precise exception when a required edge fails
     * @param int $skip The parameter offset of the first edge within the referenced function's signature
     *
     * @return list<mixed>
     */
    private function resolveArguments(
        array $edges,
        ResolutionContext $ctx,
        array|Closure $functionRef,
        int $skip = 0
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
                    $exception
                );
            }

            $args[] = $value;
        }

        return $args;
    }

    /**
     * @param class-string $className
     */
    private function resolvePropertyEdge(ResolutionPlanEdge $edge, ResolutionContext $ctx, string $className): mixed
    {
        try {
            if (!$this->tryResolveEdge($edge, $ctx, $value)) {
                throw new PropertyResolutionException(new ReflectionProperty($className, $edge->name));
            }
        } catch (ClassResolutionException $exception) {
            throw new PropertyResolutionException(new ReflectionProperty($className, $edge->name), $exception);
        }

        return $value;
    }

    /**
     * Resolves one edge: the first candidate present in the descriptor map whose instance satisfies its whole
     * conjunction wins; a soft edge falls back to its default value or <code>null</code> on failure, mirroring the
     * self-healing the injector applies to defaulted and nullable injection points.
     *
     * @param mixed $value Receives the resolved value or the soft fallback
     *
     * @return bool Whether a value was produced; <code>false</code> only for a required edge that cannot be satisfied
     * @throws ClassResolutionException If a required edge's candidate failed while resolving its own dependencies
     */
    private function tryResolveEdge(ResolutionPlanEdge $edge, ResolutionContext $ctx, mixed &$value): bool
    {
        $dependency = $edge->dependency;

        if ($dependency !== null) {
            try {
                foreach ($dependency->alternatives as $alternative) {
                    foreach ($alternative as $candidate) {
                        if (!isset($this->descriptors[$this->descriptorId($candidate, $dependency->key)])) {
                            continue;
                        }

                        $instance = $ctx->container->get($candidate, $dependency->key);

                        if (self::satisfiesAll($instance, $alternative)) {
                            $value = $instance;

                            return true;
                        }
                    }
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

    /**
     * @param non-empty-list<class-string> $conjunction
     */
    private static function satisfiesAll(object $instance, array $conjunction): bool
    {
        foreach ($conjunction as $className) {
            if (!$instance instanceof $className) {
                return false;
            }
        }

        return true;
    }
}
