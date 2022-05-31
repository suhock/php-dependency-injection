<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * @internal
 */
class ContainerDescriptor
{
    /**
     * @var Closure(class-string):LifetimeStrategy
     * @phpstan-ignore-next-line PHPStan does not support callable-level generics but complains that LifetimeStrategy
     * does not have its generic type specified
     */
    private readonly Closure $lifetimeStrategyFactory;

    /**
     * @param ContainerInterface $container
     * @param InjectorInterface $injector
     * @param callable(class-string):LifetimeStrategy $lifetimeStrategyFactory
     *
     * @phpstan-ignore-next-line PHPStan does not support callable-level generics but complains that LifetimeStrategy
     * does not have its generic type specified
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly InjectorInterface $injector,
        callable $lifetimeStrategyFactory
    ) {
        $this->lifetimeStrategyFactory = $lifetimeStrategyFactory(...);
    }

    /**
     * @template TClass
     *
     * @param class-string<TClass> $className
     * @param ContainerBuilderInterface $container
     *
     * @return bool
     */
    public function tryAdd(string $className, ContainerBuilderInterface $container): bool
    {
        if (!$this->container->has($className)) {
            return false;
        }

        $container->add(
            $className,
            /** @phpstan-ignore-next-line PHPStan gets confused resolving generic for add() */
            $this->createLifetimeStrategy($className),
            new ClosureInstanceFactory(
                $className,
                /** @phpstan-ignore-next-line PHPStan gets confused resolving generic for add() */
                fn () => $this->container->get($className),
                $this->injector
            )
        );

        return true;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return LifetimeStrategy<TClass>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function createLifetimeStrategy(string $className): LifetimeStrategy
    {
        return ($this->lifetimeStrategyFactory)($className);
    }
}
