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

use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * @internal
 * @template TClass as object
 */
class Descriptor
{
    private bool $isResolving = false;

    /**
     * @param class-string<TClass> $className
     * @param LifetimeStrategy<TClass> $lifetimeStrategy
     * @param InstanceFactory<TClass> $instanceFactory
     */
    public function __construct(
        private readonly string $className,
        private readonly LifetimeStrategy $lifetimeStrategy,
        private readonly InstanceFactory $instanceFactory
    ) {
    }

    /**
     * @return class-string<TClass>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return TClass|null
     * @throws CircularDependencyException
     */
    public function getInstance(): ?object
    {
        if ($this->isResolving) {
            throw new CircularDependencyException($this->className);
        }

        $this->isResolving = true;

        try {
            /** @psalm-suppress InvalidArgument Psalm is incorrectly inferring TClass as Descriptor for some reason */
            $instance = $this->lifetimeStrategy->get($this->instanceFactory->get(...));
        } catch (CircularDependencyException $e) {
            throw new CircularDependencyException($this->className, $e);
        } finally {
            $this->isResolving = false;
        }

        return $instance;
    }
}
