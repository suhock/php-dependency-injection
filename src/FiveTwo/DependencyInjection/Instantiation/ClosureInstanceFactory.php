<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use Closure;
use FiveTwo\DependencyInjection\DependencyInjectionException;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * @template TDependency
 * @template-implements InstanceFactory<TDependency>
 */
class ClosureInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TDependency> $className
     * @param Closure $factory
     * @param InjectorInterface $injector
     */
    public function __construct(
        private readonly string $className,
        private readonly Closure $factory,
        private readonly InjectorInterface $injector
    ) {
    }

    /**
     * @return TDependency|null
     * @throws DependencyTypeException
     * @throws DependencyInjectionException
     */
    public function get(): ?object
    {
        $result = $this->injector->call($this->factory);

        if ($result !== null && !$result instanceof $this->className) {
            throw new DependencyTypeException($this->className, $result);
        }

        return $result;
    }
}
