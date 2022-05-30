<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

/**
 * Provides an existing instance.
 *
 * @template TClass of object
 * @template-implements InstanceFactory<TClass>
 */
class ObjectInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TClass> $className The name of the class or interface provided
     * @param TClass|null $instance An instance of the indicated class, or <code>null</code>
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
     * @return TClass|null An instance of the class or <code>null</code>
     */
    public function get(): ?object
    {
        return $this->instance;
    }
}
