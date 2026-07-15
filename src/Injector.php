<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionFunction;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Injection\InjectAttributeMemberInjector;
use Suhock\DependencyInjection\Instantiation\InstantiationStrategyInterface;
use Suhock\DependencyInjection\Instantiation\PostInstantiationHookInterface;
use Suhock\DependencyInjection\Instantiation\ReflectionInstantiationStrategy;
use Suhock\DependencyInjection\Resolver\ArgumentResolver;
use Suhock\DependencyInjection\Resolver\ContainerParameterResolver;
use Suhock\DependencyInjection\Resolver\ParameterResolverInterface;

/**
 * Default implementation for {@see InjectorInterface} that resolves missing parameter values using a
 * {@see ParameterResolverInterface}. Instantiation is delegated to an {@see InstantiationStrategyInterface} (by
 * default a {@see ReflectionInstantiationStrategy}), and the new instance is then passed to a
 * {@see PostInstantiationHookInterface} (by default an {@see InjectAttributeMemberInjector} that fills its
 * {@see Inject} members). {@see InstantiationStrategyInterface} remains
 * available for callers who need to compose or supply a custom strategy.
 */
final class Injector implements InjectorInterface
{
    private readonly ArgumentResolver $argumentResolver;

    private readonly InstantiationStrategyInterface $strategy;

    private readonly PostInstantiationHookInterface $postInstantiationHook;

    /**
     * @param ParameterResolverInterface $resolver The resolver to use for resolving parameters
     * @param InstantiationStrategyInterface $strategy The instantiation strategy to use
     * @param PostInstantiationHookInterface $postInstantiationHook The hook applied to each new instance after
     *     construction
     */
    public function __construct(
        ParameterResolverInterface $resolver,
        InstantiationStrategyInterface $strategy,
        PostInstantiationHookInterface $postInstantiationHook,
    ) {
        $this->argumentResolver = new ArgumentResolver($resolver);
        $this->strategy = $strategy;
        $this->postInstantiationHook = $postInstantiationHook;
    }

    /**
     * Creates an injector that resolves parameters from the given container and instantiates classes by reflection.
     * This is the standard way to construct an injector; use the constructor directly only to supply a custom
     * resolver, instantiation strategy, or post-instantiation hook.
     *
     * @param ContainerInterface $container The container to resolve parameter values from
     * @param CacheInterface|null $cache [optional] Optional shared (L2) metadata cache; supply an {@see CacheInterface}
     *     to share the {@see InjectAttributeMemberInjector}'s reflected member metadata across requests
     */
    public static function createDefault(ContainerInterface $container, ?CacheInterface $cache = null): self
    {
        $resolver = new ContainerParameterResolver($container);
        $strategy = new ReflectionInstantiationStrategy($resolver);

        return new self($resolver, $strategy, new InjectAttributeMemberInjector($resolver, $cache));
    }

    /**
     * @inheritDoc
     */
    public function call(callable $function, array $params = []): mixed
    {
        // A callable normalized to a closure is always reflectable, so this cannot throw ReflectionException.
        $rFunction = new ReflectionFunction($function(...));

        return $rFunction->invokeArgs(
            $this->argumentResolver->resolve($rFunction->getParameters(), $params),
        );
    }

    /**
     * @inheritDoc
     */
    public function instantiate(string $className, array $params = []): object
    {
        $instance = $this->strategy->tryInstantiate($className, $params);

        if ($instance === null) {
            throw new InjectorException("No instantiation strategy could instantiate $className");
        }

        $this->postInstantiationHook->postInstantiate($instance);

        return $instance;
    }
}
