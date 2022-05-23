<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * @template TDependency
 */
class DependencyDescriptor
{
    private bool $isResolving = false;

    /**
     * @param class-string<TDependency> $className
     * @param LifetimeStrategy<TDependency> $lifetimeStrategy
     * @param InstanceFactory<TDependency> $instanceFactory
     */
    public function __construct(
        private readonly string $className,
        private readonly LifetimeStrategy $lifetimeStrategy,
        private readonly InstanceFactory $instanceFactory
    ) {
    }

    /**
     * @return class-string<TDependency>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return TDependency|null
     * @throws CircularDependencyException
     */
    public function getDependency(): ?object
    {
        if ($this->isResolving) {
            throw new CircularDependencyException($this->className);
        }

        $this->isResolving = true;

        try {
            $instance = $this->lifetimeStrategy->get($this->instanceFactory->get(...));
        } catch (CircularDependencyException $e) {
            throw new CircularDependencyException($this->className, $e);
        } finally {
            $this->isResolving = false;
        }

        return $instance;
    }
}
