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
use Suhock\DependencyInjection\Resolver\ResolutionPlanFactory;
use Suhock\DependencyInjection\Validation\ContainerValidationException;
use Suhock\DependencyInjection\Validation\ContainerValidator;
use UnitEnum;

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
        $plans = (new ResolutionPlanFactory($this->cache))->compile($descriptors);
        (new ContainerValidator($descriptors))->validate($plans);

        return new Container($descriptors, $plans, $this->injectorFactory);
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
