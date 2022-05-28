<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * @internal
 * @psalm-type LifetimeStrategyFactory = callable(class-string):LifetimeStrategy
 */
class ContainerDescriptor
{
    /** @var Closure(class-string):LifetimeStrategy */
    private readonly Closure $lifetimeStrategyFactory;

    /**
     * @param ContainerInterface $container
     * @param InjectorInterface $injector
     * @param callable $lifetimeStrategyFactory
     * @psalm-param LifetimeStrategyFactory $lifetimeStrategyFactory
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly InjectorInterface $injector,
        callable $lifetimeStrategyFactory
    ) {
        $this->lifetimeStrategyFactory = $lifetimeStrategyFactory(...);
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param ContainerBuilderInterface $container
     *
     * @return bool
     */
    public function tryAddDependency(string $className, ContainerBuilderInterface $container): bool
    {
        if (!$this->container->has($className)) {
            return false;
        }

        $container->add(
            $className,
            $this->createLifetimeStrategy($className),
            new ClosureInstanceFactory(
                $className,
                fn () => $this->container->get($className),
                $this->injector
            )
        );

        return true;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     *
     * @return LifetimeStrategy<TDependency>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function createLifetimeStrategy(string $className): LifetimeStrategy
    {
        return ($this->lifetimeStrategyFactory)($className);
    }
}
