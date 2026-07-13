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
use Suhock\DependencyInjection\Builder\ContainerBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerBuilderTrait;
use Suhock\DependencyInjection\Builder\ContainerScopedBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerScopedBuilderTrait;
use Suhock\DependencyInjection\Builder\ContainerSingletonBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerSingletonBuilderTrait;
use Suhock\DependencyInjection\Builder\ContainerTransientBuilderInterface;
use Suhock\DependencyInjection\Builder\ContainerTransientBuilderTrait;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Descriptor\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\ContextInstanceProvider;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\Resolver\ResolutionPlan;
use Suhock\DependencyInjection\Resolver\ResolutionPlanFactory;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\ConfigurationFingerprint;
use Suhock\DependencyInjection\Validation\DependencyGraph;
use Suhock\DependencyInjection\Validation\ContainerValidator;
use UnitEnum;

use function is_array;
use function is_string;

/**
 * Carries the full mutable configuration surface for building a {@see Container}. Services are added, removed, and
 * reconfigured here; {@see build()} compiles the whole dependency graph, validates it, and produces an immutable
 * {@see Container}. The builder remains usable after a failed build — fix the configuration and build again — and
 * every successful build yields a fully independent product.
 */
final class ContainerBuilder implements
    ContainerBuilderInterface,
    ContainerScopedBuilderInterface,
    ContainerSingletonBuilderInterface,
    ContainerTransientBuilderInterface
{
    use ContainerBuilderTrait;
    use ContainerScopedBuilderTrait;
    use ContainerSingletonBuilderTrait;
    use ContainerTransientBuilderTrait;
    private const GRAPH_KEY_PREFIX = 'sdi:graph:';

    /** @var array<string, Descriptor<object>> */
    private array $descriptors = [];

    /** @var Closure(ContainerInterface):InjectorInterface */
    private readonly Closure $injectorFactory;

    /**
     * @param callable(ContainerInterface):InjectorInterface $injectorFactory Provides the injector to be used in
     * conjunction with each resolution root (the built container and each scope created from it)
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata, shared by graph
     * compilation and the injector
     */
    public function __construct(
        callable $injectorFactory,
        private readonly ?CacheInterface $cache = null
    ) {
        $this->injectorFactory = $injectorFactory(...);
    }

    /**
     * Creates a builder whose product uses the default injector.
     *
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata.
     */
    public static function createDefault(?CacheInterface $cache = null): self
    {
        return new self(static fn ($container) => Injector::createDefault($container, $cache), $cache);
    }

    /**
     * Compiles the configured dependency graph, validates it, and produces an immutable {@see Container}. Every
     * added service must be resolvable: configuration defects are build errors, aggregated into one exception.
     *
     * @throws ContainerValidationException If the configuration contains any guaranteed-failure defect
     */
    public function build(): Container
    {
        $descriptors = $this->descriptors;
        self::addAutoBindings($descriptors);

        // An unchanged configuration reuses the plans a previous validated build stored under its fingerprint,
        // skipping compilation and validation entirely. Only successful builds are stored.
        $cacheKey = null;

        if ($this->cache !== null) {
            $fingerprint = ConfigurationFingerprint::compute($descriptors);

            if ($fingerprint !== null) {
                $cacheKey = self::GRAPH_KEY_PREFIX . $fingerprint;

                if ($this->cache->tryGet($cacheKey, $cached)) {
                    $plans = self::plansFromCache($cached);

                    if ($plans !== null) {
                        return new Container($descriptors, $plans, $this->injectorFactory);
                    }
                }
            }
        }

        $plans = (new ResolutionPlanFactory($this->cache))->compile($descriptors);
        (new ContainerValidator($descriptors))->validate($plans);

        if ($cacheKey !== null) {
            $this->cache?->set($cacheKey, $plans);
        }

        return new Container($descriptors, $plans, $this->injectorFactory);
    }

    /**
     * Restores a cached plan set, or <code>null</code> when the cached value does not have the expected shape.
     *
     * @return array<string, ResolutionPlan>|null
     */
    private static function plansFromCache(mixed $cached): ?array
    {
        if (!is_array($cached)) {
            return null;
        }

        $plans = [];

        foreach ($cached as $id => $plan) {
            if (!is_string($id) || !$plan instanceof ResolutionPlan) {
                return null;
            }

            $plans[$id] = $plan;
        }

        return $plans;
    }

    /**
     * Exports the dependency graph {@see build()} would produce — every service (including the automatic
     * self-bindings) and every satisfied, chosen dependency edge — as plain data for external tooling: computing the
     * graph roots that nothing injects, rendering the graph, or linting for dead services. Unsatisfiable injection
     * points produce no edge, and dependencies hidden inside custom instance providers are invisible, exactly as they
     * are to validation. Never throws: a configuration that would fail {@see build()} still exports.
     */
    public function exportDependencyGraph(): DependencyGraph
    {
        $descriptors = $this->descriptors;
        self::addAutoBindings($descriptors);
        $plans = (new ResolutionPlanFactory($this->cache))->compile($descriptors);

        return (new ContainerValidator($descriptors))->exportGraph($plans);
    }

    /**
     * Adds the services the container supplies about itself, unless the configuration already provides them:
     * {@see ContainerInterface} resolves to the current resolution root — a service resolved from a scope receives
     * that scope — and {@see ScopeFactoryInterface} resolves to the root container from any depth. Both are transient
     * so every resolution re-reads its context, and neither is disposed by the container (no self-disposal).
     *
     * @param array<string, Descriptor<object>> $descriptors
     */
    private static function addAutoBindings(array &$descriptors): void
    {
        if (!isset($descriptors[ContainerInterface::class])) {
            $descriptors[ContainerInterface::class] = self::contextDescriptor(
                ContainerInterface::class,
                static fn (ResolutionContext $context): object => $context->container
            );
        }

        if (!isset($descriptors[ScopeFactoryInterface::class])) {
            $descriptors[ScopeFactoryInterface::class] = self::contextDescriptor(
                ScopeFactoryInterface::class,
                static fn (ResolutionContext $context): object => $context->rootContext()->container
            );
        }
    }

    /**
     * A transient, never-disposed descriptor whose instance derives from the current resolution context.
     *
     * @param class-string $className
     * @param Closure(ResolutionContext):object $select
     *
     * @return Descriptor<object>
     */
    private static function contextDescriptor(string $className, Closure $select): Descriptor
    {
        return new Descriptor(
            $className,
            new TransientStrategy($className),
            new ContextInstanceProvider($className, $select),
            shouldDispose: false
        );
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
                "Class already in container for key '" . Key::getKeyFromStringOrEnum($key) . "': " .
                    $descriptor->className);
        }

        $this->descriptors[$id] = $descriptor;

        return $this;
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
     * @param class-string $className
     */
    protected function removeDescriptor(string $className, string|UnitEnum|null $key): void
    {
        unset($this->descriptors[$this->descriptorId($className, $key)]);
    }
}
