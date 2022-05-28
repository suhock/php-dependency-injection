<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

/**
 * Provides an existing instance.
 *
 * @template TDependency
 * @template-implements InstanceFactory<TDependency>
 */
class ObjectInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TDependency> $className The name of the class or interface provided
     * @param TDependency|null $instance An instance of the indicated class, or <code>null</code>
     *
     * @throws InstanceTypeException
     */
    public function __construct(
        string $className,
        private readonly ?object $instance
    ) {
        if ($instance !== null && !$instance instanceof $className) {
            throw new InstanceTypeException($className, $instance);
        }
    }

    /**
     * @inheritDoc
     * @return TDependency|null An instance of the class or <code>null</code>
     */
    public function get(): ?object
    {
        return $this->instance;
    }
}
