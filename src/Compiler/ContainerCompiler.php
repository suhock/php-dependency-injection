<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Closure;
use Override;
use Suhock\DependencyInjection\Builder\Descriptor;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InstanceProvider\ContextInstanceProvider;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\ResolutionContext;
use Suhock\DependencyInjection\ScopeFactoryInterface;
use Suhock\DependencyInjection\Validation\ContainerValidator;

use function is_array;
use function is_string;

/**
 * Default implementation for {@see ContainerCompilerInterface}.
 *
 * @internal
 */
final class ContainerCompiler implements ContainerCompilerInterface
{
    private const GRAPH_KEY_PREFIX = 'sdi:graph:';

    /**
     * @param CacheInterface|null $cache Cache used to reuse the compiled graph across compilations of an unchanged
     *     configuration
     */
    public function __construct(
        private readonly ?CacheInterface $cache,
    ) {}

    /**
     * Creates a compiler with the default configuration.
     *
     * @param CacheInterface|null $cache [optional] Cache used to reuse the compiled graph across compilations of an
     *     unchanged configuration
     */
    public static function createDefault(?CacheInterface $cache = null): self
    {
        return new self($cache);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function compile(array $descriptors): Container
    {
        self::addAutoBindings($descriptors);

        $cacheKey = $this->getCacheKey($descriptors);

        // If the configuration has not changed since its plans were cached, reuse the plans.
        if ($cacheKey !== null && $this->cache !== null && $this->cache->tryGet($cacheKey, $cached)) {
            $plans = self::plansFromCache($cached);

            if ($plans !== null) {
                return new Container($descriptors, $plans);
            }
        }

        // Cache miss. Compile the plans.
        $plans = new ResolutionPlanFactory()->compile($descriptors);
        new ContainerValidator($descriptors)->validate($plans);

        if ($cacheKey !== null) {
            $this->cache?->set($cacheKey, $plans);
        }

        return new Container($descriptors, $plans);
    }

    /**
     * @param array<string, Descriptor<object>> $descriptors
     */
    private function getCacheKey(array $descriptors): ?string
    {
        if ($this->cache === null) {
            return null;
        }

        $fingerprint = ConfigurationFingerprint::compute($descriptors);

        if ($fingerprint === null) {
            return null;
        }

        return self::GRAPH_KEY_PREFIX . $fingerprint;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function exportGraph(array $descriptors): DependencyGraph
    {
        self::addAutoBindings($descriptors);
        $plans = new ResolutionPlanFactory()->compile($descriptors);

        return new ContainerValidator($descriptors)->exportGraph($plans);
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
}
