<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Suhock\DependencyInjection\ResolutionContext;

/**
 * Interface for classes that manage the provision of objects.
 *
 * @template TClass of object
 *
 * @internal The set of instance providers is closed; add services through the {@see \Suhock\DependencyInjection\ContainerBuilder} convenience methods (classes, factories, instances, implementations) instead of implementing this.
 */
interface InstanceProviderInterface
{
    /**
     * @param ResolutionContext $context The context of the resolution root to resolve the instance's dependencies from
     *
     * @return TClass An instance of the class
     */
    public function get(ResolutionContext $context): object;
}
