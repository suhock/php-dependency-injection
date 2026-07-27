<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Override;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Compiler\ContainerCompiler;
use Suhock\DependencyInjection\Compiler\ContainerCompilerInterface;
use Suhock\DependencyInjection\Compiler\DependencyGraph;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Lifetime\ScopedStrategy;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use UnitEnum;

/**
 * Carries the full mutable configuration surface for building a {@see Container}. Services are added, removed, and
 * reconfigured here. {@see build()} hands the configuration to a {@see ContainerCompilerInterface}, which compiles
 * the whole dependency graph, validates it, and produces an immutable {@see Container}. The builder remains usable
 * after a failed build, and every successful build yields a fully independent {@see Container}.
 */
final class ContainerBuilder implements ContainerBuilderInterface
{
    /** @var array<string, Descriptor<object>> */
    private array $descriptors = [];

    /**
     * @param ContainerCompilerInterface $compiler The compiler that turns this builder's configuration into a
     *     container
     */
    public function __construct(
        private readonly ContainerCompilerInterface $compiler,
    ) {}

    /**
     * Creates a builder with the default configuration.
     *
     * @param CacheInterface|null $cache [optional] Cache used to memoize reflected metadata and to reuse the
     *     compiled graph across builds of an unchanged configuration
     */
    public static function createDefault(?CacheInterface $cache = null): self
    {
        return new self(ContainerCompiler::createDefault($cache));
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
        return $this->compiler->compile($this->descriptors);
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
        return $this->compiler->exportGraph($this->descriptors);
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
