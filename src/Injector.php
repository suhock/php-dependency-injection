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
use ReflectionFunction;
use Suhock\DependencyInjection\Instantiation\InstantiationStrategyInterface;
use Suhock\DependencyInjection\Instantiation\ReflectionInstantiationStrategy;
use Suhock\DependencyInjection\Resolver\ArgumentResolver;
use Suhock\DependencyInjection\Resolver\ContainerParameterResolver;
use Suhock\DependencyInjection\Resolver\ParameterResolverInterface;

/**
 * Default implementation for {@see InjectorInterface} that resolves missing parameter values using a
 * {@see ParameterResolverInterface}. Instantiation is delegated to an {@see InstantiationStrategyInterface} (by
 * default a {@see ReflectionInstantiationStrategy}). {@see InstantiationStrategyInterface} remains available for
 * callers who need to compose or supply a custom strategy.
 */
final class Injector implements InjectorInterface
{
    private readonly ArgumentResolver $argumentResolver;

    private readonly InstantiationStrategyInterface $strategy;

    /**
     * @param ParameterResolverInterface $resolver The resolver to use for resolving parameters
     * @param InstantiationStrategyInterface $strategy The instantiation strategy to use
     */
    public function __construct(
        ParameterResolverInterface $resolver,
        InstantiationStrategyInterface $strategy,
    ) {
        $this->argumentResolver = new ArgumentResolver($resolver);
        $this->strategy = $strategy;
    }

    /**
     * Creates an injector that resolves parameters from the given container and instantiates classes by reflection.
     * This is the standard way to construct an injector; use the constructor directly only to supply a custom
     * resolver or instantiation strategy.
     *
     * @param ContainerInterface $container The container to resolve parameter values from
     */
    public static function createDefault(ContainerInterface $container): self
    {
        $resolver = new ContainerParameterResolver($container);

        return new self($resolver, new ReflectionInstantiationStrategy($resolver));
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (any callable is valid here; $params supplies what injection cannot)
    #[Override]
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
    #[Override]
    public function instantiate(string $className, array $params = []): object
    {
        $instance = $this->strategy->tryInstantiate($className, $params);

        if ($instance === null) {
            throw new InjectorException("$className could not be instantiated");
        }

        return $instance;
    }
}
