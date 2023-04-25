<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

/**
 * Factory that provides a previously constructed instance of the class.
 *
 * @template TClass of object
 * @template-implements InstanceProvider<TClass>
 * @psalm-immutable
 */
class ObjectInstanceProvider implements InstanceProvider
{
    /**
     * @param class-string<TClass> $className The name of the class or interface provided
     * @param TClass $instance An instance of the indicated class
     *
     * @throws InstanceTypeException
     */
    public function __construct(
        string $className,
        private readonly object $instance
    ) {
        if (!$instance instanceof $className) {
            throw new InstanceTypeException($className, $instance);
        }
    }

    /**
     * @inheritDoc
     * @return TClass An instance of the class
     */
    public function get(): object
    {
        return $this->instance;
    }
}
