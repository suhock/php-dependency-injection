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
 * A factory for providing instances using a factory method.
 *
 * @template TClass of object
 * @template-implements InstanceFactory<TClass>
 */
class ClosureInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TClass> $className The name of the class this factory will provide
     * @param Closure $factory The factory that will be used for providing instances
     * @param InjectorInterface $injector The injector that will be used for invoking the factory method
     */
    public function __construct(
        private readonly string $className,
        private readonly Closure $factory,
        private readonly InjectorInterface $injector
    ) {
    }

    /**
     * @inheritDoc
     * @return TClass|null An instance of the class or <code>null</code>
     * @throws InstanceTypeException
     * @throws DependencyInjectionException
     */
    public function get(): ?object
    {
        $result = $this->injector->call($this->factory);

        if ($result !== null && !$result instanceof $this->className) {
            throw new InstanceTypeException($this->className, $result);
        }

        return $result;
    }
}
