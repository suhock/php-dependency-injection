<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

/**
 * Provides instances from an existing instance.
 *
 * @template TDependency
 * @template-implements InstanceFactory<TDependency>
 */
class ObjectInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
     *
     * @throws DependencyTypeException
     */
    public function __construct(
        string $className,
        private readonly ?object $instance
    ) {
        if ($instance !== null && !$instance instanceof $className) {
            throw new DependencyTypeException($className, $instance);
        }
    }

    /**
     * @return TDependency|null
     */
    public function get(): ?object
    {
        return $this->instance;
    }
}
