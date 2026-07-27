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
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\InstanceProvider\ContextInstanceProvider;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Lifetime\ScopedStrategy;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\Resolver\ResolutionPlan;
use Suhock\DependencyInjection\Resolver\ResolutionPlanFactory;
use Suhock\DependencyInjection\Validation\ConfigurationFingerprint;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\ContainerValidator;
use Suhock\DependencyInjection\Validation\DependencyGraph;
use UnitEnum;

use function is_array;
use function is_string;

/**
 * Carries the full mutable configuration surface for building a {@see Container}. Services are added, removed, and
 * reconfigured here; {@see build()} compiles the whole dependency graph, validates it, and produces an immutable
 * {@see Container}. The builder remains usable after a failed build (fix the configuration and build again), and
 * every successful build yields a fully independent product.
 */
final class ContainerBuilder implements ContainerBuilderInterface
{
    private const GRAPH_KEY_PREFIX = 'sdi:graph:';

    /** @var array<string, Descriptor<object>> */
    private array $descriptors = [];

    /**
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata and to reuse the
     *     compiled graph across builds of an unchanged configuration
     */
    public function __construct(
        private readonly ?CacheInterface $cache = null,
    ) {}

    /**
     * Creates a builder with the default configuration.
     *
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata.
     */
    public static function createDefault(?CacheInterface $cache = null): self
    {
        return new self($cache);
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addSingleton(
        string $className,
        string|callable|object|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->add(
            $className,
            new SingletonStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addScoped(
        string $className,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->add(
            $className,
            new ScopedStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addTransient(
        string $className,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->add(
            $className,
            new TransientStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|callable|object|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyed(
            $className,
            $key,
            new SingletonStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyed(
            $className,
            $key,
            new ScopedStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|callable|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyed(
            $className,
            $key,
            new TransientStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @param class-string $className The class name of the service to remove
     * @param string|UnitEnum|null $key [optional] The key of the service to remove, or null for the unkeyed service
     *
     * @return $this
     */
    #[Override]
    public function remove(string $className, string|UnitEnum|null $key = null): static
    {
        $this->removeDescriptor($className, $key);

        return $this;
    }

    /**
     * @param callable(static):mixed $configure
     *
     * @return $this
     */
    #[Override]
    public function configure(callable $configure): static
    {
        $configure($this);

        return $this;
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
                        return new Container($descriptors, $plans);
                    }
                }
            }
        }

        $plans = (new ResolutionPlanFactory())->compile($descriptors);
        (new ContainerValidator($descriptors))->validate($plans);

        if ($cacheKey !== null) {
            $this->cache?->set($cacheKey, $plans);
        }

        return new Container($descriptors, $plans);
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
     * Exports the dependency graph {@see build()} would produce, every service (including the automatic
     * self-bindings) and every satisfied, chosen dependency edge, as plain data for external tooling: computing the
     * graph roots that nothing injects, rendering the graph, or linting for dead services. Unsatisfiable injection
     * points produce no edge, and dependencies hidden inside custom instance providers are invisible, exactly as they
     * are to validation. Never throws: a configuration that would fail {@see build()} still exports.
     */
    public function exportDependencyGraph(): DependencyGraph
    {
        $descriptors = $this->descriptors;
        self::addAutoBindings($descriptors);
        $plans = (new ResolutionPlanFactory())->compile($descriptors);

        return (new ContainerValidator($descriptors))->exportGraph($plans);
    }

    /**
     * Adds the services the container supplies about itself, unless the configuration already provides them:
     * {@see ContainerInterface} resolves to the current resolution root (a service resolved from a scope receives
     * that scope), and {@see ScopeFactoryInterface} resolves to the root container from any depth. Both are transient
     * so every resolution re-reads its context, and neither is disposed by the container (no self-disposal).
     *
     * @param array<string, Descriptor<object>> $descriptors
     */
    private static function addAutoBindings(array &$descriptors): void
    {
        if (!isset($descriptors[ContainerInterface::class])) {
            $descriptors[ContainerInterface::class] = self::contextDescriptor(
                ContainerInterface::class,
                static fn(ResolutionContext $context): object => $context->container,
            );
        }

        if (!isset($descriptors[ScopeFactoryInterface::class])) {
            $descriptors[ScopeFactoryInterface::class] = self::contextDescriptor(
                ScopeFactoryInterface::class,
                static fn(ResolutionContext $context): object => $context->rootContext()->container,
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
            shouldDispose: false,
        );
    }

    /**
     * Adds an instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to add
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service; pass false when their disposal is the responsibility of something outside the container
     */
    private function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->addDescriptor(new Descriptor($className, $lifetimeStrategy, $instanceProvider, $shouldDispose));
    }

    /**
     * Adds a keyed instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to add
     * @param string|UnitEnum $key The key of the service
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service; pass false when their disposal is the responsibility of something outside the container
     */
    private function addKeyed(
        string $className,
        string|UnitEnum $key,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->addDescriptor(
            new Descriptor($className, $lifetimeStrategy, $instanceProvider, $shouldDispose),
            $key,
        );
    }

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     *
     * @return $this
     */
    private function addDescriptor(Descriptor $descriptor, string|UnitEnum|null $key = null): self
    {
        $id = DescriptorId::compute($descriptor->className, $key);

        if (isset($this->descriptors[$id])) {
            throw new ContainerException($key === null
                ? 'Class already in container: ' . $descriptor->className
                : "Class already in container for key '" . Key::getKeyFromStringOrEnum($key) . "': "
                    . $descriptor->className);
        }

        $this->descriptors[$id] = $descriptor;

        return $this;
    }

    /**
     * @param class-string $className
     */
    private function removeDescriptor(string $className, string|UnitEnum|null $key): void
    {
        unset($this->descriptors[DescriptorId::compute($className, $key)]);
    }
}
